<?php

namespace App\Support;

final class SimpegFieldRules
{
    public const DIGITS_ONLY = 'regex:/\A[0-9]+\z/';

    public const CONTAINS_LETTER = 'regex:/\p{L}/u';
}
