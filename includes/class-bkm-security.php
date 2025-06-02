<?php

class BKM_Security {
    
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    public static function sanitize_input($input, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($input);
            case 'text':
                return sanitize_text_field($input);
            case 'textarea':
                return sanitize_textarea_field($input);
            case 'int':
                return intval($input);
            case 'date':
                return sanitize_text_field($input); // Ek tarih validasyonu eklenebilir
            default:
                return sanitize_text_field($input);
        }
    }
    
    public static function check_user_permission($capability = 'read') {
        return current_user_can($capability);
    }
    
    public static function validate_required_fields($fields, $data) {
        $errors = array();
        
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                $errors[] = sprintf('%s alanı zorunludur.', ucfirst($field));
            }
        }
        
        return $errors;
    }
}