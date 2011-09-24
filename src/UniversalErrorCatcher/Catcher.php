<?php

/**
 * 
 * @author Kotlyar Maksim kotlyar.maksim@gmail.com
 */
class UniversalErrorCatcher_Catcher
{
   /**
    * 
    * @var string
    */
    protected $memoryReserv = '';
    
    /**
     *
     * @var mixed
     */
    protected $callbacks = array();

    /**
     *
     * @var boolean
     */
    protected $isStarted = false;
    
    /**
     *
     * @param mixed $callback
     * 
     * @throws InvalidArgumentException if invalid callback provided
     * 
     * @return UniversalErrorHandler_Handler 
     */
    public function registerCallback($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Invalid callback provided.');
        }
        
        $this->callbacks[] = $callback;
        
        return $this;
    }
    
    /**
     *
     * @param mixed $callbackToUnregister
     * 
     * @return UniversalErrorHandler_Handler 
     */
    public function unregisterCallback($callbackToUnregister)
    {
        foreach ($this->callbacks as $key => $callback) {
            if ($callbackToUnregister == $callback) {
                unset($this->callbacks[$key]);
            }
        }
        
        return $this;
    }
  
    /**
     *
     * @return void
     */
    public function start()
    {
        if ($this->isStarted) return;

        $this->memoryReserv = str_repeat('x', 1024 * 500);

        // it needs to be done to find out whether the error comes from the ordinary code or it is under @
        // it could be any less zero values
        0 == error_reporting() &&  @error_reporting(-1);

        set_error_handler(array($this, 'handleError'));
        register_shutdown_function(array($this, 'handleFatalError'));
        set_exception_handler(array($this, 'handleException'));

        $this->isStarted = true;
    }

    /**
     *
     * @param Exception $e
     *
     * @return void
     */
    public function handleException(Exception $e)
    {
        foreach ($this->callbacks as $callback) {
            call_user_func_array($callback, array($e));
        }
    }

    /**
     *
     * @param string $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     *
     * @return ErrorException
     */
    public function handleError($errno, $errstr, $errfile, $errline)
    {
        $this->handleException(new ErrorException($errstr, 0, $errno, $errfile, $errline));

        return false;
    }

    /**
     *
     * @return void
     */
    public function handleFatalError()
    {
        $fatals = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR);
        
        $error = $this->getFatalError();
        if ($error && isset($error['type']) && in_array($error['type'], $fatals)) {

            $this->freeMemory();

            @$this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     *
     * It is done for testing purpose
     *
     * @return array
     */
    protected function getFatalError()
    {
        return error_get_last();
    }

    /**
     *
     * @return void
     */
    protected function freeMemory()
    {
        unset($this->memoryReserv);
    }
}