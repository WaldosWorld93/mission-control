<?php

namespace App\Enums;

enum ArtifactType: string
{
    case Document = 'document';
    case Code = 'code';
    case Image = 'image';
    case Data = 'data';
    case Other = 'other';
}
