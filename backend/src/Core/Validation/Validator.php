<?php

declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Exceptions\ValidationException;
use DateTimeImmutable;

/**
 * Validador de entrada baseado em regras em texto.
 *
 * Segurança:
 *  - Retorna SOMENTE os campos declarados nas regras (campos extras são
 *    descartados — impede mass assignment, ex.: enviar "role").
 *  - Strings passam por trim + strip_tags + remoção de caracteres de
 *    controle, a menos que a regra "raw" seja usada (ex.: senhas).
 *  - Toda string tem tamanho máximo (padrão 255) para evitar abuso.
 *
 * Regras: required, nullable, sometimes, string, raw, text, int, numeric,
 * money, bool, email, date, time, month, in:a,b, min:n, max:n, digits_between:a,b,
 * array (lista), object (chave => valor), ids (lista de inteiros positivos), password, regex:/.../
 */
final class Validator
{
    private const DEFAULT_MAX = 255;

    /**
     * @param array<string, mixed>  $input
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    public function validate(array $input, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = $this->parse($ruleString);
            $present = array_key_exists($field, $input);
            $value = $input[$field] ?? null;

            if (isset($ruleList['present']) && !$present) {
                $errors[$field] = 'Campo obrigatório.';
                continue;
            }
            if (isset($ruleList['sometimes']) && !$present) {
                continue;
            }

            if (is_string($value) && !isset($ruleList['raw'])) {
                $value = $this->sanitizeString($value, isset($ruleList['text']));
            }

            $empty = $value === null || $value === '' || $value === [];
            if ($empty && isset($ruleList['present']) && $present && is_array($value)) {
                $clean[$field] = [];
                continue;
            }
            if ($empty) {
                if (isset($ruleList['required'])) {
                    $errors[$field] = 'Campo obrigatório.';
                } elseif ($present || isset($ruleList['nullable'])) {
                    $clean[$field] = null;
                }
                continue;
            }

            $error = null;
            $value = $this->applyRules($value, $ruleList, $error);
            if ($error !== null) {
                $errors[$field] = $error;
                continue;
            }
            $clean[$field] = $value;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $clean;
    }

    /**
     * @param array<string, string|null> $rules
     */
    private function applyRules(mixed $value, array $rules, ?string &$error): mixed
    {
        // Conversões de tipo primeiro.
        if (isset($rules['int'])) {
            if (is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
                $error = 'Deve ser um número inteiro.';
                return null;
            }
            $value = (int) $value;
        } elseif (isset($rules['money'])) {
            $normalized = is_string($value) ? str_replace(',', '.', $value) : $value;
            if (!is_numeric($normalized) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $normalized)) {
                $error = 'Valor monetário inválido (use até 2 casas decimais).';
                return null;
            }
            $value = number_format((float) $normalized, 2, '.', '');
        } elseif (isset($rules['numeric'])) {
            if (!is_numeric($value)) {
                $error = 'Deve ser numérico.';
                return null;
            }
            $value = $value + 0;
        } elseif (isset($rules['bool'])) {
            $bool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($bool === null) {
                $error = 'Deve ser verdadeiro ou falso.';
                return null;
            }
            $value = $bool;
        } elseif (isset($rules['object'])) {
            if (!is_array($value) || ($value !== [] && array_is_list($value))) {
                $error = 'Deve ser um objeto.';
                return null;
            }
        } elseif (isset($rules['array']) || isset($rules['ids'])) {
            if (!is_array($value) || !array_is_list($value)) {
                $error = 'Deve ser uma lista.';
                return null;
            }
            if (isset($rules['ids'])) {
                $ids = [];
                foreach ($value as $item) {
                    if (filter_var($item, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                        $error = 'Lista de identificadores inválida.';
                        return null;
                    }
                    $ids[] = (int) $item;
                }
                $value = array_values(array_unique($ids));
            }
        } elseif (!is_string($value) && !is_int($value) && !is_float($value)) {
            $error = 'Formato inválido.';
            return null;
        } else {
            $value = (string) $value;
        }

        if (is_string($value)) {
            $max = isset($rules['max']) ? (int) $rules['max'] : (isset($rules['text']) ? 5000 : self::DEFAULT_MAX);
            if (mb_strlen($value) > $max) {
                $error = "Máximo de {$max} caracteres.";
                return null;
            }
            if (isset($rules['min']) && mb_strlen($value) < (int) $rules['min']) {
                $error = "Mínimo de {$rules['min']} caracteres.";
                return null;
            }
        } elseif (is_int($value) || is_float($value)) {
            if (isset($rules['min']) && $value < (float) $rules['min']) {
                $error = "Valor mínimo: {$rules['min']}.";
                return null;
            }
            if (isset($rules['max']) && $value > (float) $rules['max']) {
                $error = "Valor máximo: {$rules['max']}.";
                return null;
            }
        } elseif (is_array($value) && isset($rules['max']) && count($value) > (int) $rules['max']) {
            $error = "Máximo de {$rules['max']} itens.";
            return null;
        }

        if (isset($rules['email']) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'E-mail inválido.';
            return null;
        }
        if (isset($rules['email'])) {
            $value = mb_strtolower((string) $value);
        }

        if (isset($rules['date'])) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
            if ($date === false || $date->format('Y-m-d') !== $value) {
                $error = 'Data inválida (use AAAA-MM-DD).';
                return null;
            }
        }

        if (isset($rules['month'])) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value)) {
                $error = 'Mês inválido (use AAAA-MM).';
                return null;
            }
        }

        if (isset($rules['time']) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $value)) {
            $error = 'Horário inválido (use HH:MM).';
            return null;
        }

        if (isset($rules['digits_between'])) {
            [$a, $b] = array_map('intval', explode(',', (string) $rules['digits_between']));
            if (!preg_match('/^\d{' . $a . ',' . $b . '}$/', (string) $value)) {
                $error = "Deve conter de {$a} a {$b} dígitos.";
                return null;
            }
        }

        if (isset($rules['in'])) {
            $options = explode(',', (string) $rules['in']);
            if (!in_array((string) $value, $options, true)) {
                $error = 'Valor não permitido.';
                return null;
            }
        }

        if (isset($rules['regex']) && !preg_match((string) $rules['regex'], (string) $value)) {
            $error = 'Formato inválido.';
            return null;
        }

        if (isset($rules['password'])) {
            $pwd = (string) $value;
            if (mb_strlen($pwd) < 10 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
                $error = 'A senha deve ter no mínimo 10 caracteres, com letras e números.';
                return null;
            }
        }

        return $value;
    }

    private function sanitizeString(string $value, bool $multiline): string
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }
        $value = strip_tags($value);
        // Remove caracteres de controle (mantém \n e \t em textos longos).
        $value = (string) preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $value);

        return trim($value);
    }

    /** @return array<string, string|null> */
    private function parse(string $rules): array
    {
        $parsed = [];
        // "regex:" pode conter "|" — tratado por último.
        if (preg_match('/(?:^|\|)regex:(.+)$/', $rules, $m)) {
            $parsed['regex'] = $m[1];
            $rules = (string) preg_replace('/(?:^|\|)regex:.+$/', '', $rules);
        }
        foreach (array_filter(explode('|', $rules)) as $rule) {
            [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
            $parsed[$name] = $param ?? '';
        }

        return $parsed;
    }
}
