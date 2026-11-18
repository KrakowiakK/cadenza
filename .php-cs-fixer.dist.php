<?php

// One coding standard for the three packages: the Symfony rules, as in Symfony itself.
return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'native_function_invocation' => ['include' => ['@compiler_optimized'], 'scope' => 'namespaced'],
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in([__DIR__.'/harness/src', __DIR__.'/harness/tests', __DIR__.'/runtime-bundle/src', __DIR__.'/runtime-bundle/tests', __DIR__.'/demo-app/src', __DIR__.'/demo-app/tests', __DIR__.'/library'])
    );
