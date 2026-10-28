<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Catalog;

/**
 * Checks and normalises node props against the node type's schema. Errors are written for the agent to fix.
 */
final class PropValidator
{
    private const COLOR = '/^(#[0-9a-f]{3,8}|(oklch|rgb|rgba|hsl|hsla)\([0-9a-z.,%\s\/+-]+\))$/i';

    /**
     * @param array<string, mixed> $props
     *
     * @return array{0: array<string, mixed>, 1: list<string>} normalised props and errors
     */
    public function validate(NodeType $type, array $props, bool $complete): array
    {
        $errors = [];
        $normalised = [];

        foreach ($props as $name => $value) {
            if (!isset($type->props[$name])) {
                $errors[] = \sprintf('%s has no prop "%s" (props: %s).', $type->name, $name, implode(', ', array_keys($type->props)) ?: 'none');
                continue;
            }
            if (null === $value) {
                $normalised[$name] = null;
                continue;
            }
            [$value, $error] = $this->check($type->props[$name], $value, $type->name.'.'.$name);
            if (null !== $error) {
                $errors[] = $error;
                continue;
            }
            $normalised[$name] = $value;
        }

        if ($complete) {
            foreach ($type->props as $name => $schema) {
                if (($schema['required'] ?? false) && (!isset($normalised[$name]) || '' === $normalised[$name])) {
                    $errors[] = \sprintf('%s.%s is required.', $type->name, $name);
                }
            }
        }

        return [$normalised, $errors];
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array{0: mixed, 1: ?string}
     */
    private function check(array $schema, mixed $value, string $path): array
    {
        switch ($schema['type']) {
            case 'string':
            case 'text':
            case 'markdown':
                if (\is_int($value) || \is_float($value)) {
                    $value = (string) $value;
                }

                return \is_string($value) ? [$value, null] : [null, $path.' must be a string.'];
            case 'image':
                if (!\is_string($value) || !preg_match('~^/media/[A-Za-z0-9._-]+$~', $value)) {
                    return [null, $path.' must be an image from the site media library ("/media/…"); import images first (media tools), or leave it empty.'];
                }

                return [$value, null];
            case 'file':
                if (!\is_string($value) || !preg_match('~^/files/[A-Za-z0-9._-]+$~', $value)) {
                    return [null, $path.' must be a document stored by the site ("/files/…"); store it first (add_document), or leave it empty.'];
                }

                return [$value, null];
                        case 'url':
                if (!\is_string($value)) {
                    return [null, $path.' must be a URL string.'];
                }
                $ok = str_starts_with($value, '/') || str_starts_with($value, '#') || preg_match('~^(https?://|mailto:|tel:)~i', $value);

                return $ok ? [$value, null] : [null, $path.' must be an absolute URL (https://…), a site path (/…), mailto: or tel:.'];
            case 'date':
                if (\is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value)) && checkdate((int) substr(trim($value), 5, 2), (int) substr(trim($value), 8, 2), (int) substr(trim($value), 0, 4))) {
                    return [trim($value), null];
                }

                return [null, $path.' must be a date YYYY-MM-DD, e.g. "2027-06-14".'];
            case 'time':
                return \is_string($value) && preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', trim($value)) ? [trim($value), null] : [null, $path.' must be a time HH:MM, e.g. "15:00".'];
            case 'color':
                return \is_string($value) && preg_match(self::COLOR, trim($value)) ? [trim($value), null] : [null, $path.' must be a CSS color (#hex, oklch(), rgb(), hsl()).'];
            case 'int':
                return is_numeric($value) ? [(int) $value, null] : [null, $path.' must be an integer.'];
            case 'bool':
                return \is_bool($value) ? [$value, null] : [null, $path.' must be true or false.'];
            case 'enum':
                foreach ($schema['values'] as $allowed) {
                    if ($allowed === $value || (\is_int($allowed) && is_numeric($value) && (int) $value === $allowed)) {
                        return [$allowed, null];
                    }
                }

                return [null, \sprintf('%s must be one of %s, got %s.', $path, json_encode($schema['values']), json_encode($value))];
            case 'list':
                if (!\is_array($value) || !array_is_list($value)) {
                    return [null, $path.' must be a list.'];
                }
                $out = [];
                foreach ($value as $i => $item) {
                    if (isset($schema['items'])) {
                        if (!\is_array($item)) {
                            return [null, \sprintf('%s[%d] must be an object with %s.', $path, $i, implode(', ', array_keys($schema['items'])))];
                        }
                        $row = [];
                        foreach ($item as $key => $v) {
                            if (!isset($schema['items'][$key])) {
                                return [null, \sprintf('%s[%d] has no field "%s" (fields: %s).', $path, $i, $key, implode(', ', array_keys($schema['items'])))];
                            }
                            if (null === $v) {
                                continue;
                            }
                            [$v, $error] = $this->check($schema['items'][$key], $v, \sprintf('%s[%d].%s', $path, $i, $key));
                            if (null !== $error) {
                                return [null, $error];
                            }
                            $row[$key] = $v;
                        }
                        foreach ($schema['items'] as $key => $itemSchema) {
                            if (($itemSchema['required'] ?? false) && !isset($row[$key])) {
                                return [null, \sprintf('%s[%d].%s is required.', $path, $i, $key)];
                            }
                        }
                        $out[] = $row;
                    } else {
                        [$v, $error] = $this->check(['type' => $schema['of'] ?? 'string'], $item, \sprintf('%s[%d]', $path, $i));
                        if (null !== $error) {
                            return [null, $error];
                        }
                        $out[] = $v;
                    }
                }

                return [$out, null];
        }

        return [null, $path.' has an unsupported schema type.'];
    }
}
