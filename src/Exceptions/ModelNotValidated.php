<?php
namespace Codethereal\Database\Sqlite\Exceptions;

class ModelNotValidated extends \Exception
{
    /**
     * @var array
     */
    private array $errorMessages;

    public function __construct($message, $code = 0, Exception $previous = null, array $errorMessages)
    {
        $this->errorMessages = $errorMessages;
        parent::__construct($message, $code, $previous);
    }

    public function getErrors()
    {
        return $this->errorMessages;
    }
}