<?php

namespace App\Enums;

enum MeetingImportStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';
}
