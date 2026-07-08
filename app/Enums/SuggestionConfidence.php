<?php

namespace App\Enums;

enum SuggestionConfidence: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
}
