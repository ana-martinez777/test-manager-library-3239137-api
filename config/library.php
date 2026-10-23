<?php

/*
 * Configuración de Manager Library (plan §2). Todos los secretos vienen del entorno (artículo IX).
 */
return [
    'jwt_secret' => env('JWT_SECRET'),
    'jwt_ttl_seconds' => 8 * 3600,
    'bcrypt_cost' => (int) env('BCRYPT_COST', 12),
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),
    'covers_dir' => env('COVERS_DIR') ?: storage_path('app/covers'),
];
