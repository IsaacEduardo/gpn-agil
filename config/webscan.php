<?php

return [
    // O agente só pode escutar em loopback; esta URL não é uma rota Laravel.
    'agent_url' => env('WEBSCAN_AGENT_URL', 'http://127.0.0.1:18090'),
];
