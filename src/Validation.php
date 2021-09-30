<?php
namespace Codethereal\Database\Sqlite;

class Validation
{
    /**
     * @var array
     */
    private array $error;

    /**
     * @var object
     */
    private object $lang;

    /**
     * @var array
     */
    private array $data;

    /**
     * @param $validations
     * @param $data
     * @return bool
     */
    public function validate($validations, $data)
    {
        $this->data = $data;
        foreach ($validations as $field => $validation){
            $label = $validation['label'];
            $rules = explode("|", $validation['rules']); // Split rules from "|"
            foreach ($rules as $rule){
                $fieldValue = $this->data[$field] ?? ""; // Field's value which comes from post
                if (preg_match("/(.*?)\[(.*)\]/",$rule, $match)){ // Check if there is parametered rule like min[5]
                    $ruleName = $match[1];
                    $ruleValue = $match[2];

                    if(!call_user_func_array([$this, $ruleName], [$fieldValue, $ruleValue])){
                        $this->error[$field][] = $this->translate($ruleName, ["field" => $label, "param" => $ruleValue]); // If there is an error, write it with given language
                    }
                }else{ // If no parametered rule
                    if(!call_user_func_array([$this, $rule], [$fieldValue])){
                        $this->error[$field][] = $this->translate($rule, ["field" => $label]);
                    }
                }
            }
        }
        return count((array)$this->error) > 0 ? false : true;
    }

    /**
     * @param string|null $langJson
     */
    public function setLang(string $langJson = null)
    {
        if ($langJson !== null){
            $this->lang = json_decode(file_get_contents($langJson));
        }else{
            $langFile = __DIR__ . "/Asset/en.json";
            $this->lang = json_decode(file_get_contents($langFile));
        }
    }

    /**
     * Returns all the errors if exists
     * @return array
     */
    public function errors()
    {
        return count($this->error) > 0 ? $this->error : [];
    }

    /**
     * Minimum Length Validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function min($field, $satisfier)
    {
        return mb_strlen($field) >= $satisfier;
    }

    /**
     * Maximum Length Validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function max($field, $satisfier)
    {
        return mb_strlen($field) <= $satisfier;
    }

    /**
     * Exact Length Validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function exact($field, $satisfier)
    {
        return mb_strlen($field) == $satisfier;
    }

    /**
     * Less than given number validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function less($field, $satisfier)
    {
        return $field < $satisfier && (filter_var($field,FILTER_VALIDATE_INT) || filter_var($field,FILTER_VALIDATE_FLOAT));
    }

    /**
     * Less or equal than given number validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function lesseq($field, $satisfier)
    {
        return $field <= $satisfier && (filter_var($field,FILTER_VALIDATE_INT) || filter_var($field,FILTER_VALIDATE_FLOAT));
    }

    /**
     * Greater than given number validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function greater($field, $satisfier)
    {
        return $field > $satisfier && (filter_var($field,FILTER_VALIDATE_INT) || filter_var($field,FILTER_VALIDATE_FLOAT));
    }

    /**
     * Greater or equal than given number validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function greatereq($field, $satisfier)
    {
        return $field >= $satisfier && (filter_var($field,FILTER_VALIDATE_INT) || filter_var($field,FILTER_VALIDATE_FLOAT));
    }

    /**
     * Matches with given post value validation
     * @param $field
     * @param $satisfier
     * @return bool
     */
    private function match($field, $satisfier)
    {
        return $field === $this->data[$satisfier];
    }

    /**
     * E-Mail Validation
     * @param $field
     * @return bool
     */
    private function email($field)
    {
        return filter_var($field, FILTER_VALIDATE_EMAIL) ? true : false;
    }

    /**
     * Integer validation
     * @param $field
     * @return bool
     */
    private function int($field)
    {
        return filter_var($field, FILTER_VALIDATE_INT) ? true : false;
    }

    /**
     * Required validation
     * @param $field
     * @return bool
     */
    private function required($field)
    {
        return !empty($field);
    }

    /**
     * Translates the validation errors
     * @param $key
     * @param array $options
     * @return string|string[]
     */
    private function translate($key, $options = [])
    {
        $options['field'] ??= "";
        $options['param'] ??= "";
        $errorMessage = $this->lang->{$key};
        $errorMessage = str_replace("{field}",$options['field'],$errorMessage);
        $errorMessage = str_replace("{param}",$options['param'],$errorMessage);
        return $errorMessage;
    }
}