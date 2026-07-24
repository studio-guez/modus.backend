<?php

echo json_encode([
    'succes'  => $success ?? false,
    'alert'   => [
        'error' => $alert['error'] ?? null,
    ],
    'data'    => $data ?? false,
]);
