<?php

namespace Dniccum\Vaultr\Enums;

enum EnvironmentType: string
{
    case Local = 'local';
    case Development = 'development';
    case Production = 'production';
}
