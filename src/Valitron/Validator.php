<?php
namespace Valitron;

use InvalidArgumentException;

/**
 * Validation Class
 *
 * Validates input against certain criteria
 *
 * @package Valitron
 * @author Vance Lucas <vance@vancelucas.com>
 * @link http://www.vancelucas.com/
 */
class Validator
{
    /**
     * @var string
     */
    const ERROR_DEFAULT = 'Invalid';

    /**
     * @var array
     */
    protected $_fields = array();

    /**
     * @var array
     */
    protected $_errors = array();

    /**
     * @var array
     */
    protected $_validations = array();

    /**
     * @var array
     */
    protected $_labels = array();

    /**
     * @var string
     */
    protected static $_lang;

    /**
     * @var string
     */
    protected static $_langDir;

    /**
     * @var array
     */
    protected static $_rules = array();

    /**
     * @var array
     */
    protected static $_ruleMessages = array();

    /**
     * @var array
     */
    protected $validUrlPrefixes = array('http://', 'https://', 'ftp://');

    /**
     * Setup validation
     *
     * @param  array                     $data
     * @param  array                     $fields
     * @param  string                    $lang
     * @param  string                    $langDir
     * @throws \InvalidArgumentException
     */
    public function __construct($data, $fields = array(), $lang = null, $langDir = null)
    {
        // Allows filtering of used input fields against optional second array of field names allowed
        // This is useful for limiting raw $_POST or $_GET data to only known fields
        $this->_fields = !empty($fields) ? array_intersect_key($data, array_flip($fields)) : $data;

        // set lang in the follow order: constructor param, static::$_lang, default to en
        $lang = $lang ?: static::lang();

        // set langDir in the follow order: constructor param, static::$_langDir, default to package lang dir
        $langDir = $langDir ?: static::langDir();

        // Load language file in directory
        $langFile = rtrim($langDir, '/') . '/' . $lang . '.php';
        if (stream_resolve_include_path($langFile) ) {
            $langMessages = include $langFile;
            static::$_ruleMessages = array_merge(static::$_ruleMessages, $langMessages);
        } else {
            throw new \InvalidArgumentException("fail to load language file '$langFile'");
        }
    }

    /**
     * Get/set language to use for validation messages
     *
     * @param  string $lang
     * @return string
     */
    public static function lang($lang = null)
    {
        if ($lang !== null) {
            static::$_lang = $lang;
        }

        return static::$_lang ?: 'en';
    }

    /**
     * Get/set language file path
     *
     * @param  string $dir
     * @return string
     */
    public static function langDir($dir = null)
    {
        if ($dir !== null) {
            static::$_langDir = $dir;
        }

        return static::$_langDir ?: dirname(dirname(__DIR__)) . '/lang';
    }

    /**
     * Required field validator
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateRequired($field, $value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }

    /**
     * Validate that two values match
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateEquals($field, $value, array $params)
    {
        $field2 = $params[0];

        return isset($this->_fields[$field2]) && $value == $this->_fields[$field2];
    }

    /**
     * Validate that a field is different from another field
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateDifferent($field, $value, array $params)
    {
        $field2 = $params[0];

        return isset($this->_fields[$field2]) && $value != $this->_fields[$field2];
    }

    /**
     * Validate that a field was "accepted" (based on PHP's string evaluation rules)
     *
     * This validation rule implies the field is "required"
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateAccepted($field, $value)
    {
        $acceptable = array('yes', 'on', 1, true);

        return $this->validateRequired($field, $value) && in_array($value, $acceptable, true);
    }

    /**
     * Validate that a field is an array
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateArray($field, $value)
    {
        return is_array($value);
    }

    /**
     * Validate that a field is numeric
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateNumeric($field, $value)
    {
        return is_numeric($value);
    }

    /**
     * Validate that a field is an integer
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateInteger($field, $value)
    {
        return filter_var($value, \FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Validate the length of a string
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateLength($field, $value, $params)
    {
        $length = $this->stringLength($value);
        // Length between
        if (isset($params[1])) {
            return $length >= $params[0] && $length <= $params[1];
        }
        // Length same
        return $length == $params[0];
    }

    /**
     * Validate the length of a string (between)
     *
     * @param  string  $field
     * @param  mixed   $value
     * @param  array   $params
     * @return boolean
     */
    protected function validateLengthBetween($field, $value, $params)
    {
        $length = $this->stringLength($value);

        return $length >= $params[0] && $length <= $params[1];
    }

    /**
     * Validate the length of a string (min)
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     *
     * @return boolean
     */
    protected function validateLengthMin($field, $value, $params)
    {
        return $this->stringLength($value) >= $params[0];
    }

    /**
     * Validate the length of a string (max)
     *
     * @param string $field
     * @param mixed  $value
     * @param array  $params
     *
     * @return boolean
     */
    protected function validateLengthMax($field, $value, $params)
    {
        return $this->stringLength($value) <= $params[0];
    }

    /**
     * Get the length of a string
     *
     * @param  string $value
     * @return int
     */
    protected function stringLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }

    /**
     * Validate the size of a field is greater than a minimum value.
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateMin($field, $value, $params)
    {
        if (function_exists('bccomp')) {
            return !(bccomp($params[0], $value, 14) == 1);
        } else {
            return $params[0] <= $value;
        }
    }

    /**
     * Validate the size of a field is less than a maximum value
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateMax($field, $value, $params)
    {
        if (function_exists('bccomp')) {
            return !(bccomp($value, $params[0], 14) == 1);
        } else {
            return $params[0] >= $value;
        }
    }

    /**
     * Validate a field is contained within a list of values
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateIn($field, $value, $params)
    {
        $isAssoc = array_values($params[0]) !== $params[0];
        if ($isAssoc) {
            $params[0] = array_keys($params[0]);
        }

        return in_array($value, $params[0]);
    }

    /**
     * Validate a field is not contained within a list of values
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateNotIn($field, $value, $params)
    {
        return !$this->validateIn($field, $value, $params);
    }

    /**
     * Validate a field contains a given string
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @return bool
     */
    protected function validateContains($field, $value, $params)
    {
        if (!isset($params[0])) {
            return false;
        }
        if (!is_string($params[0]) || !is_string($value)) {
            return false;
        }

        return (strpos($value, $params[0]) !== false);
    }

    /**
     * Validate that a field is a valid IP address
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateIp($field, $value)
    {
        return filter_var($value, \FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Validate that a field is a valid e-mail address
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateEmail($field, $value)
    {
        return filter_var($value, \FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate that a field is a valid URL by syntax
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateUrl($field, $value)
    {
        foreach ($this->validUrlPrefixes as $prefix) {
            if (strpos($value, $prefix) !== false) {
                return filter_var($value, \FILTER_VALIDATE_URL) !== false;
            }
        }

        return false;
    }

    /**
     * Validate that a field is an active URL by verifying DNS record
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateUrlActive($field, $value)
    {
        foreach ($this->validUrlPrefixes as $prefix) {
            if (strpos($value, $prefix) !== false) {
                $url = str_replace($prefix, '', strtolower($value));

                return checkdnsrr($url);
            }
        }

        return false;
    }

    /**
     * Validate that a field contains only alphabetic characters
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateAlpha($field, $value)
    {
        return preg_match('/^([a-z])+$/i', $value);
    }

    /**
     * Validate that a field contains only alpha-numeric characters
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateAlphaNum($field, $value)
    {
        return preg_match('/^([a-z0-9])+$/i', $value);
    }

    /**
     * Validate that a field contains only alpha-numeric characters, dashes, and underscores
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateSlug($field, $value)
    {
        return preg_match('/^([-a-z0-9_-])+$/i', $value);
    }

    /**
     * Validate that a field passes a regular expression check
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @return bool
     */
    protected function validateRegex($field, $value, $params)
    {
        return preg_match($params[0], $value);
    }

    /**
     * Validate that a field is a valid date
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateDate($field, $value)
    {
        $isDate = false;
        if ($value instanceof \DateTime) {
            $isDate = true;
        } else {
            $isDate = strtotime($value) !== false;
        }

        return $isDate;
    }

    /**
     * Validate that a field matches a date format
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateDateFormat($field, $value, $params)
    {
        $parsed = date_parse_from_format($params[0], $value);

        return $parsed['error_count'] === 0 && $parsed['warning_count'] === 0;
    }

    /**
     * Validate the date is before a given date
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateDateBefore($field, $value, $params)
    {
        $vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : strtotime($value);
        $ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

        return $vtime < $ptime;
    }

    /**
     * Validate the date is after a given date
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @internal param array $fields
     * @return bool
     */
    protected function validateDateAfter($field, $value, $params)
    {
        $vtime = ($value instanceof \DateTime) ? $value->getTimestamp() : strtotime($value);
        $ptime = ($params[0] instanceof \DateTime) ? $params[0]->getTimestamp() : strtotime($params[0]);

        return $vtime > $ptime;
    }

    /**
     * Validate that a field contains a boolean.
     *
     * @param  string $field
     * @param  mixed  $value
     * @return bool
     */
    protected function validateBoolean($field, $value)
    {
        return (is_bool($value)) ? true : false;
    }

    /**
     * Validate that a field contains a valid credit card
     * optionally filtered by an array
     *
     * @param  string $field
     * @param  mixed  $value
     * @param  array  $params
     * @return bool
     */
    protected function validateCreditCard($field, $value, $params)
    {
        /**
         * I there has been an array of valid cards supplied, or the name of the users card
         * or the name and an array of valid cards
         */
        if (!empty($params)) {
            /**
             * array of valid cards
             */
            if (is_array($params[0])) {
                $cards = $params[0];
            } elseif (is_string($params[0])) {
                $cardType  = $params[0];
                if (isset($params[1]) && is_array($params[1])) {
                    $cards = $params[1];
                    if (!in_array($cardType, $cards)) {
                        return false;
                    }
                }
            }
        }
        /**
         * Luhn algorithm
         *
         * @return bool
         */
        $numberIsValid = function () use ($value) {
            $number = preg_replace('/[^0-9]+/', '', $value);
            $sum = 0;

            $strlen = strlen($number);
            if ($strlen < 13) {
                return false;
            }
            for ($i = 0; $i < $strlen; $i++) {
                $digit = (int) substr($number, $strlen - $i - 1, 1);
                if ($i % 2 == 1) {
                    $sub_total = $digit * 2;
                    if ($sub_total > 9) {
                        $sub_total = ($sub_total - 10) + 1;
                    }
                } else {
                    $sub_total = $digit;
                }
                $sum += $sub_total;
            }
            if ($sum > 0 && $sum % 10 == 0) {
                    return true;
            }

            return false;
        };

        if ($numberIsValid()) {
            if (!isset($cards)) {
                return true;
            } else {
                $cardRegex = array(
                    'visa'          => '#^4[0-9]{12}(?:[0-9]{3})?$#',
                    'mastercard'    => '#^5[1-5][0-9]{14}$#',
                    'amex'          => '#^3[47][0-9]{13}$#',
                    'dinersclub'    => '#^3(?:0[0-5]|[68][0-9])[0-9]{11}$#',
                    'discover'      => '#^6(?:011|5[0-9]{2})[0-9]{12}$#',
                );

                if (isset($cardType)) {
                    // if we don't have any valid cards specified and the card we've been given isn't in our regex array
                    if (!isset($cards) && !in_array($cardType, array_keys($cardRegex))) {
                        return false;
                    }

                    // we only need to test against one card type
                    return (preg_match($cardRegex[$cardType], $value) === 1);

                } elseif (isset($cards)) {
                    // if we have cards, check our users card against only the ones we have
                    foreach ($cards as $card) {
                        if (in_array($card, array_keys($cardRegex))) {
                            // if the card is valid, we want to stop looping
                            if (preg_match($cardRegex[$card], $value) === 1) {
                                return true;
                            }
                        }
                    }
                } else {
                    // loop through every card
                    foreach ($cardRegex as $regex) {
                        // until we find a valid one
                        if (preg_match($regex, $value) === 1) {
                            return true;
                        }
                    }
                }
            }
        }

        // if we've got this far, the card has passed no validation so it's invalid!
        return false;
    }

    protected function validateInstanceOf($field, $value, $params)
    {
        $isInstanceOf = false;
        if (is_object($value)) {
            if (is_object($params[0]) && $value instanceof $params[0]) {
                $isInstanceOf = true;
            }
            if (get_class($value) === $params[0]) {
                $isInstanceOf = true;
            }
        }
        if (is_string($value)) {
            if (is_string($params[0]) && get_class($value) === $params[0]) {
                $isInstanceOf = true;
            }
        }

        return $isInstanceOf;
    }

    /**
     *  Get array of fields and data
     *
     * @return array
     */
    public function data()
    {
        return $this->_fields;
    }

    /**
     * Get array of error messages
     *
     * @param  null|string $field
     * @return array|bool
     */
    public function errors($field = null)
    {
        if ($field !== null) {
            return isset($this->_errors[$field]) ? $this->_errors[$field] : false;
        }

        return $this->_errors;
    }

    /**
     * Add an error to error messages array
     *
     * @param string $field
     * @param string $msg
     * @param array  $params
     */
    public function error($field, $msg, array $params = array())
    {
        $this->_errors[$field][] = $this->renderErrorMessage($field, $msg, $params);
        
    }
    
    private function renderErrorMessage($field, $msg, array $params = array())
    {
        $msg = $this->checkAndSetLabel($field, $msg, $params);

        $values = array();
        // Printed values need to be in string format
        foreach ($params as $param) {
            if (is_array($param)) {
                $param = "['" . implode("', '", $param) . "']";
            }
            if ($param instanceof \DateTime) {
                $param = $param->format('Y-m-d');
            } else {
                if (is_object($param)) {
                    $param = get_class($param);
                }
            }
            // Use custom label instead of field name if set
            if (is_string($params[0])) {
                if (isset($this->_labels[$param])) {
                    $param = $this->_labels[$param];
                }
            }
            $values[] = $param;
        }

        return vsprintf($msg, $values);
    }

    /**
     * Specify validation message to use for error for the last validation rule
     *
     * @param  string $msg
     * @return $this
     */
    public function message($msg)
    {
        $this->_validations[count($this->_validations) - 1]['message'] = $msg;

        return $this;
    }

    /**
     * Reset object properties
     */
    public function reset()
    {
        $this->_fields = array();
        $this->_errors = array();
        $this->_validations = array();
        $this->_labels = array();
    }

    protected function getPart($data, $identifiers)
    {
        // Catches the case where the field is an array of discrete values
        if (is_array($identifiers) && count($identifiers) === 0) {
            return array($data, false);
        }

        $identifier = array_shift($identifiers);

        // Glob match
        if ($identifier === '*') {
            $values = array();
            foreach ($data as $row) {
                list($value, $multiple) = $this->getPart($row, $identifiers);
                if ($multiple) {
                    $values = array_merge($values, $value);
                } else {
                    $values[] = $value;
                }
            }

            return array($values, true);
        }

        // Dead end, abort
        elseif ($identifier === NULL || ! isset($data[$identifier])) {
            return array(null, false);
        }

        // Match array element
        elseif (count($identifiers) === 0) {
            return array($data[$identifier], false);
        }

        // We need to go deeper
        else {
            return $this->getPart($data[$identifier], $identifiers);
        }
    }

    /**
     * Run validations and return boolean result
     *
     * @return boolean
     */
    public function validate()
    {
        foreach ($this->_validations as $v) {
            foreach ($v['fields'] as $field) {
                 list($values, $multiple) = $this->getPart($this->_fields, explode('.', $field));

                // Don't validate if the field is not required and the value is empty
                if ($v['rule'] !== 'required' && !$this->hasRule('required', $field) && (! isset($values) || $values === '' || ($multiple && count($values) == 0))) {
                    continue;
                }

                // Callback is user-specified or assumed method on class
                if (isset(static::$_rules[$v['rule']])) {
                    $callback = static::$_rules[$v['rule']];
                } else {
                    $callback = array($this, 'validate' . ucfirst($v['rule']));
                }

                if (!$multiple) {
                    $values = array($values);
                }

                $result = true;
                foreach ($values as $value) {
                    $result = $result && call_user_func($callback, $field, $value, $v['params']);
                }

                if (!$result) {
                    $this->error($field, $v['message'], $v['params']);
                }
            }
        }

        return count($this->errors()) === 0;
    }

    /**
     * Determine whether a field is being validated by the given rule.
     *
     * @param  string  $name  The name of the rule
     * @param  string  $field The name of the field
     * @return boolean
     */
    protected function hasRule($name, $field)
    {
        foreach ($this->_validations as $validation) {
            if ($validation['rule'] == $name) {
                if (in_array($field, $validation['fields'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Register new validation rule callback
     *
     * @param  string                    $name
     * @param  mixed                     $callback
     * @param  string                    $message
     * @throws \InvalidArgumentException
     */
    public static function addRule($name, $callback, $message = self::ERROR_DEFAULT)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Second argument must be a valid callback. Given argument was not callable.');
        }

        static::$_rules[$name] = $callback;
        static::$_ruleMessages[$name] = $message;
    }

    /**
     * Convenience method to add a single validation rule
     *
     * @param  string                    $rule
     * @param  array                     $fields
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function rule($rule, $fields)
    {
        if (!isset(static::$_rules[$rule])) {
            $ruleMethod = 'validate' . ucfirst($rule);
            if (!method_exists($this, $ruleMethod)) {
                throw new \InvalidArgumentException("Rule '" . $rule . "' has not been registered with " . __CLASS__ . "::addRule().");
            }
        }

        // Ensure rule has an accompanying message
        $message = isset(static::$_ruleMessages[$rule]) ? static::$_ruleMessages[$rule] : self::ERROR_DEFAULT;

        // Get any other arguments passed to function
        $params = array_slice(func_get_args(), 2);

        $this->_validations[] = array(
            'rule' => $rule,
            'fields' => (array) $fields,
            'params' => (array) $params,
            'message' => '{field} ' . $message
        );

        return $this;
    }

    /**
     * @param  string $value
     * @internal param array $labels
     * @return $this
     */
    public function label($value)
    {
        $lastRules = $this->_validations[count($this->_validations) - 1]['fields'];
        $this->labels(array($lastRules[0] => $value));

        return $this;
    }

    /**
     * @param  array  $labels
     * @return string
     */
    public function labels($labels = array())
    {
        $this->_labels = array_merge($this->_labels, $labels);

        return $this;
    }

    /**
     * @param  string $field
     * @param  string $msg
     * @param  array  $params
     * @return array
     */
    private function checkAndSetLabel($field, $msg, $params)
    {
        if (isset($this->_labels[$field])) {
            $msg = str_replace('{field}', $this->_labels[$field], $msg);

            if (is_array($params)) {
                $i = 1;
                foreach ($params as $k => $v) {
                    $tag = '{field'. $i .'}';
                    $label = isset($params[$k]) && (is_numeric($params[$k]) || is_string($params[$k])) && isset($this->_labels[$params[$k]]) ? $this->_labels[$params[$k]] : $tag;
                    $msg = str_replace($tag, $label, $msg);
                    $i++;
                }
            }
        } else {
            $msg = str_replace('{field}', ucwords(str_replace('_', ' ', $field)), $msg);
        }

        return $msg;
    }

    /**
     * Convenience method to add multiple validation rules with an array
     *
     * @param array $rules
     */
    public function rules($rules)
    {
        foreach ($rules as $ruleType => $params) {
            if (is_array($params)) {
                foreach ($params as $innerParams) {
                    array_unshift($innerParams, $ruleType);
                    call_user_func_array(array($this, 'rule'), $innerParams);
                }
            } else {
                $this->rule($ruleType, $params);
            }
        }
    }
    
    public function exportRules($clientValidator = "bootstrapvalidator", $options = array()){ 
        if ($clientValidator == "bootstrapvalidator"){
            return $this->exportRulesBootstrapValidator($options);
        } else {
            error_log("Unsupported client validator type in exportRulesJSON (only 'bootstrapvalidator' is currently supported).");
            return false;            
        }    
    }
    
    public function exportRulesBootstrapValidator($options = array()){
        $exportType = isset($options['exportType']) ? $options['exportType'] : "json";
        $prettyPrint = isset($options['prettyPrint']) ? $options['prettyPrint'] : false;
        if ($exportType == "json"){
            $json = $this->exportRulesBootstrapValidatorJSON();
            if ($prettyPrint)
                return json_encode($json, JSON_PRETTY_PRINT);
            else
                return json_encode($json);

        }else if ($exportType == "data"){
            $json = $this->exportRulesBootstrapValidatorJSON();
            $result = array();
            // Convert json to data strings
            foreach ($json as $field_name => $field){
                $result[$field_name] = "";
                if (!isset($field['validators']))
                    continue;
                
                foreach ($field['validators'] as $validator_name => $validator){
                    $result[$field_name] .= "data-bv-" . strtolower($validator_name) . "=true ";
                    foreach ($validator as $param_name => $param_val){
                        $result[$field_name] .= "data-bv-" . strtolower($validator_name) . "-" . strtolower($param_name) . "=\"" . strtolower($param_val) . "\" ";
                    }
                }
            }

            return $result;   
        
        } else {
            error_log("Unsupported exportType in exportRules (must be 'json' or 'data').");
            return false;
        }   
    }
    
    public function exportRulesBootstrapValidatorJSON(){
        $result = array();
        // Build scaffold for all fields
        foreach ($this->_fields as $field_name => $field){
            $result[$field_name] = array();
            $result[$field_name]['validators'] = array();
        }
                    
        // Find all relevant validators for this field
        foreach ($this->_validations as $v) {
            foreach ($v['fields'] as $field_name) {             
                if (isset($field_name, $this->_fields)) {
                    $rule = $this->mapBootstrapValidatorRule($v['rule'], $v['params']);
                    if ($rule) {
                        $keys = array_keys($rule);
                        $clientRuleName = $keys[0];
                        
                        // If the client rule already exists, attempt to merge                        
                        if (isset($result[$field_name]['validators'][$clientRuleName])){
                            $params = array();
                            if (isset($rule[$clientRuleName]['min'])){
                                if (isset($result[$field_name]['validators'][$clientRuleName]['min'])){
                                    $result[$field_name]['validators'][$clientRuleName]['min'] = max(array($rule[$clientRuleName]['min'], $result[$field_name]['validators'][$clientRuleName]['min']));
                                } else {
                                    $result[$field_name]['validators'][$clientRuleName]['min'] = $rule[$clientRuleName]['min'];
                                }
                            }
                            if (isset($rule[$clientRuleName]['max'])){
                                if (isset($result[$field_name]['validators'][$clientRuleName]['max'])){
                                    $result[$field_name]['validators'][$clientRuleName]['max'] = min(array($rule[$clientRuleName]['max'], $result[$field_name]['validators'][$clientRuleName]['max']));
                                } else {
                                    $result[$field_name]['validators'][$clientRuleName]['max'] = $rule[$clientRuleName]['max'];
                                }
                            }
                            // Update message
                            $min = isset($result[$field_name]['validators'][$clientRuleName]['min']) ? $result[$field_name]['validators'][$clientRuleName]['min'] : null;
                            $max = isset($result[$field_name]['validators'][$clientRuleName]['max']) ? $result[$field_name]['validators'][$clientRuleName]['max'] : null;
                            if ($min && $max)
                                if ($min == $max)
                                    $result[$field_name]['validators'][$clientRuleName]['message'] = $this->renderErrorMessage($field_name, $field_name . " "  . static::$_ruleMessages['length'], array($min, $max));
                                else
                                    $result[$field_name]['validators'][$clientRuleName]['message'] = $this->renderErrorMessage($field_name, $field_name . " "  . static::$_ruleMessages['lengthBetween'], array($min, $max));
                            else if ($min)
                                $result[$field_name]['validators'][$clientRuleName]['message'] = $this->renderErrorMessage($field_name, $field_name . " "  . static::$_ruleMessages['lengthMin'], array($min));
                            else if ($max)
                                $result[$field_name]['validators'][$clientRuleName]['message'] = $this->renderErrorMessage($field_name, $field_name . " "  . static::$_ruleMessages['lengthMax'], array($max)); 
                        } else {                      
                            // Add the rule and message
                            if ($v['message']) {
                                $rule[$clientRuleName]['message'] = $this->renderErrorMessage($field_name, $v['message'], $v['params']);
                            }   
                            $result[$field_name]['validators'][$clientRuleName] = $rule[$clientRuleName];
                        }
                        
                        // If the rule is an "identical" or "different" rule, add the rule to the other field
                        if (($clientRuleName == "identical") || ($clientRuleName == "different")){
                            $field_name_other = $v['params'][0];
                            $params = array($field_name);
                            $rule = $this->mapBootstrapValidatorRule($v['rule'], $params);
                            if ($rule) {
                                $keys = array_keys($rule);
                                $clientRuleName = $keys[0];
                                if ($v['message']) {
                                    $rule[$clientRuleName]['message'] = $this->renderErrorMessage($field_name_other, $v['message'], $params);
                                }   
                                    
                                $result[$field_name_other]['validators'][$clientRuleName] = $rule[$clientRuleName];
                            }
                        }
                        
                        
                    }
                }
            }
        }
        return $result;
    }
    
    public function mapBootstrapValidatorRule($ruleName, $params){
        $rule = array();
        
        switch ($ruleName){    
            case "required" :
                $rule["notEmpty"] = array();
                break;
            case "different" :
                $rule["different"] = array();
                $rule["different"]["field"] = $params[0];
                break;
            case "equals" :
                $rule["identical"] = array();
                $rule["identical"]["field"] = $params[0];
                break;            
            case "accepted" :
                $rule["choice"] = array();
                $rule["choice"]["min"] = 1;
                break;
            case "numeric" :
                $rule["numeric"] = array();
                break;
            case "integer" :
                $rule["integer"] = array();
                break;
            case "length" :
                $rule["stringLength"] = array();
                $rule["stringLength"]["min"] = $params[0];
                $rule["stringLength"]["max"] = $params[0];
                break;                
            case "lengthBetween" :
                $rule["stringLength"] = array();
                $rule["stringLength"]["min"] = $params[0];
                $rule["stringLength"]["max"] = $params[1];
                break;               
            case "lengthMin" :
                $rule["stringLength"] = array();
                $rule["stringLength"]["min"] = $params[0];
                break;                
            case "lengthMax" :
                $rule["stringLength"] = array();
                $rule["stringLength"]["max"] = $params[0];
                break; 
            case "min" :
                $rule["greaterThan"] = array();
                $rule["greaterThan"]["value"] = $params[0];
                $rule["greaterThan"]["inclusive"] = "true";
                break; 
            case "max" :
                $rule["lessThan"] = array();
                $rule["lessThan"]["value"] = $params[0];
                $rule["lessThan"]["inclusive"] = "true";
                break;                 
            case "in" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/^" . implode("|", $params[0]) . "$/i";
                break;
            case "notIn" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/^?!(" . implode("|", $params[0]) . "$)/i";
                break;            
            case "ip" :
                $rule["ip"] = array();
                $rule["ip"]["ipv6"] = false;
                break;                 
            case "email" :
                $rule["emailAddress"] = array();
                break;   
            case "url" :
                $rule["uri"] = array();
                break;
            case "urlActive" :
                $rule["uri"] = array();
                break;
            case "alpha" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/^[a-zA-Z]*$/i";
                break;              
            case "alphaNum" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/^[a-zA-Z0-9]*$/i";
                break;                   
            case "slug" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/^[a-z0-9\-_]*$/i";
                break;   
            case "regex" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = $params[0];
                break;  
            case "date" :
                $rule["date"] = array();            // Note: this validates against only one format, MM/DD/YYYY
                break;                   
            case "contains" :
                $rule["regexp"] = array();
                $rule["regexp"]["regexp"] = "/" . $params[0] . "/i";
                break;
            case "creditCard" :
                $rule["creditCard"] = array();
                break;
                
            // Unsupported validators
            case "dateFormat" :  return null;
            // TODO: convert PHP's formats into bootstrapValidator's http://bootstrapvalidator.com/validators/date/ formats      
            case "dateBefore" : return null;        // Date comparison not supported in bootstrapValidator
            case "dateAfter" : return null;        // Date comparison not supported in bootstrapValidator     
            case "instanceOf" : return null;
            case "array" : return null;
        }
                    
        return $rule;
    }
    
    
}
