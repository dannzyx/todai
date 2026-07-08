<?php

namespace App\Enums;

enum TaskSource: string
{
    case Manual = 'manual';
    case Chat = 'chat';
    case Fireflies = 'fireflies';
}
