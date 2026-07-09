<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
