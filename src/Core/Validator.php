<?php
namespace Core;

class Validator {
    private $errors = [];
    private $data = [];

    public function validate($data, $rules) {
        $this->errors = [];
        $this->data = $data;

        foreach ($rules as $field => $fieldRules) {
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $rule);
            }
        }

        return empty($this->errors);
    }

    private function applyRule($field, $rule) {
        $value = $this->data[$field] ?? null;

        switch ($rule) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, 'Field is required');
                }
                break;

            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'Invalid URL format');
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'Invalid email format');
                }
                break;

            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum(str_replace(['-', '_'], '', $value))) {
                    $this->addError($field, 'Only letters, numbers, hyphens and underscores allowed');
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'Must be a number');
                }
                break;

            case 'port':
                if (empty($value)) return; // Port is optional
                if (!is_numeric($value) || $value < 1 || $value > 65535) {
                    $this->addError($field, 'Invalid port number');
                }
                break;
                
            case 'boolean':
                // For checkboxes, isset() is sufficient since unchecked boxes aren't sent
                if (!is_null($value)) {
                    return;
                }
                $this->addError($field, 'Must be a boolean value');
                break;

            case 'slug':
                if (!empty($value) && !preg_match('/^[a-z0-9-]+$/', $value)) {
                    $this->addError($field, 'Only lowercase letters, numbers, and hyphens allowed');
                }
                break;
        }

        // Handle min/max length rules
        if (strpos($rule, 'min:') === 0) {
            $min = (int)substr($rule, 4);
            if (strlen($value) < $min) {
                $this->addError($field, "Must be at least $min characters");
            }
        }

        if (strpos($rule, 'max:') === 0) {
            $max = (int)substr($rule, 4);
            if (strlen($value) > $max) {
                $this->addError($field, "Must not exceed $max characters");
            }
        }
    }

    public function sanitize($data, $rules) {
        $sanitized = [];
        foreach ($rules as $field => $rule) {
            if (isset($data[$field])) {
                $sanitized[$field] = $this->sanitizeField($data[$field], $rule);
            }
        }
        return $sanitized;
    }

    private function sanitizeField($value, $rule) {
        switch ($rule) {
            case 'string':
                return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
            
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            
            case 'int':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            
            default:
                return $value;
        }
    }

    public function getErrors() {
        return $this->errors;
    }

    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
}