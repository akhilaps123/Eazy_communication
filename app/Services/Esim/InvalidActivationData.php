<?php

namespace App\Services\Esim;

use RuntimeException;

/** Activation data is missing or malformed. Messages name the field, never its value. */
class InvalidActivationData extends RuntimeException {}
