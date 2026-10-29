<?php

namespace App\Enums;

enum BlockType: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Quote = 'quote';
    case ListItem = 'list_item';
    case Poetry = 'poetry';
}
