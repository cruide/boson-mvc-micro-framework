<?php namespace Boson\Traits;

trait ClassName 
{
    public function className($clear = false)
    {
        return $clear ? end( explode('\\', get_called_class()) )
                      : get_called_class();
    }
}