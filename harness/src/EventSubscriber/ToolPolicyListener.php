<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Media\ImageGenerator;
use App\Repository\ProposalRepository;
use App\Studio\Turn;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * How the agent works is enforced here, not only asked for in the prompt: the owner sees a sketch before any
 * image hunting starts, and image tools have a budget per turn.
 */
final class ToolPolicyListener
{
    private const IMAGE_TOOLS = ['find_images', 'offer_images', 'generate_image'];
    private const IMAGE_BUDGET = 3;

    private const PROPOSAL_ATTEMPTS = 6;

    private int $imageCalls = 0;
    private int $proposalAttempts = 0;
    private bool $waitingForOwner = false;
    private ?string $turnKey = null;

    public function __construct(
        private readonly Turn $turn,
        private readonly ProposalRepository $proposals,
        private readonly ImageGenerator $generator,
        private readonly ToolTraceSubscriber $trace,
    ) {
    }

    #[AsEventListener(priority: 10)]
    public function __invoke(ToolCallRequested $event): void
    {
        $name = $event->getToolCall()->getName();
        $key = spl_object_hash($this->turn).($this->turn->conversation?->getId() ?? '');
        if ($key !== $this->turnKey) {
            $this->turnKey = $key;
            $this->imageCalls = $this->proposalAttempts = 0;
            $this->waitingForOwner = false;
        }

        if (\in_array($name, ['photos_from_phone', 'connect_account', 'import_content'], true)) {
            $this->waitingForOwner = true;

            return;
        }
        if (\in_array($name, ['propose_changes', 'propose_variants'], true)) {
            $reason = match (true) {
                $this->waitingForOwner => 'Wait: the owner is sending photos / importing content. Do not build anything this turn; you will get a message when it is done. Reply briefly now.',
                ++$this->proposalAttempts > self::PROPOSAL_ATTEMPTS => 'Too many attempts this turn. Stop, and tell the owner in one sentence what you could not do.',
                default => null,
            };
            if (null !== $reason) {
                $event->deny($reason);
                $this->trace->finish($name, false, $reason);
            }

            return;
        }
        if (!\in_array($name, self::IMAGE_TOOLS, true)) {
            return;
        }

        $reason = null;
        if ('generate_image' === $name && !$this->generator->isAvailable()) {
            $reason = 'Image generation is not configured on this installation. Use offer_images or leave the image empty.';
        } elseif ('find_images' !== $name && [] === $this->turn->proposals && (null === $this->turn->conversation || [] === $this->proposals->open($this->turn->conversation))) {
            $reason = 'Not yet: first call propose_changes so the owner sees the sketch (leave image props empty). Then offer images for the new node ids.';
        } elseif (++$this->imageCalls > self::IMAGE_BUDGET) {
            $reason = 'Image budget for this turn is used up. Finish: reply to the owner (they can ask for more photos).';
        }

        if (null !== $reason) {
            $event->deny($reason);
            $this->trace->finish($name, false, $reason);
        }
    }
}
