<?php
/**
 * Tiny validation helper.
 *
 * Usage:
 *   $v = new Validator($_POST, [
 *       'email'    => ['required', 'email'],
 *       'password' => ['required', 'min:6'],
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }
 */

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->run();
    }

    public function fails(): bool   { return !empty($this->errors); }
    public function passes(): bool  { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function first(): ?string {
        foreach ($this->errors as $list) {
            if (!empty($list)) return $list[0];
        }
        return null;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            foreach ((array) $rules as $rule) {
                $this->apply($field, $value, $rule);
            }
        }
    }

    private function apply(string $field, $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        switch ($name) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->errors[$field][] = ucfirst($field) . ' is required.';
                }
                break;
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be a valid email.';
                }
                break;
            case 'min':
                if ($value !== null && mb_strlen((string) $value) < (int) $param) {
                    $this->errors[$field][] = ucfirst($field) . " must be at least $param characters.";
                }
                break;
            case 'max':
                if ($value !== null && mb_strlen((string) $value) > (int) $param) {
                    $this->errors[$field][] = ucfirst($field) . " must be at most $param characters.";
                }
                break;
            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    $this->errors[$field][] = ucfirst($field) . ' must be numeric.';
                }
                break;
            case 'in':
                $list = explode(',', (string) $param);
                if ($value !== null && !in_array((string) $value, $list, true)) {
                    $this->errors[$field][] = ucfirst($field) . ' is invalid.';
                }
                break;
        }
    }
}
