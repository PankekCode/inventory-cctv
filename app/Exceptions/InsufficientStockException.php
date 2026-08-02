<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    protected $message = 'Stock tidak mencukupi untuk transaksi ini.';
}