<?php

namespace Dniccum\SecretStash\Enums;

enum EnvironmentType: string
{
    case Local = 'local';
    case Development = 'development';
    case Production = 'production';
}
