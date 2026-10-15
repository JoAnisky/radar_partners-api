<?php

namespace App\Signal;

enum StackMatch
{
    case Match;
    case Review;
    case Excluded;
}
