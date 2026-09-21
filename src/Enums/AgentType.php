<?php

namespace Dniccum\SecretStash\Enums;

enum AgentType: string
{
    case ClaudeCode = 'claude_code';
    case Cursor = 'cursor';
    case Codex = 'codex';
    case OpenAi = 'open_ai';
    case Custom = 'custom';
}
