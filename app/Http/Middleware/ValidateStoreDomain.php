<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Store;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateStoreDomain
{
public function handle(Request $request, Closure $next): Response
{
// 1. Permitir peticiones pre-flight de CORS sin validar el dominio
if ($request->isMethod('OPTIONS')) {
return $next($request);
}

// 2. Verificar existencia del header X-Store-Api-Key
$apiKey = $request->header('X-Store-Api-Key');

if (!$apiKey) {
return response()->json(['error' => 'API Key no proporcionada.'], 401);
}

// 3. Buscar la tienda
$store = Store::where('api_key', $apiKey)->first();

if (!$store) {
return response()->json(['error' => 'API Key inválida.'], 401);
}

// 4. Si la tienda no tiene un dominio configurado, se permite la petición (útil para pruebas/dev)
if (empty($store->allowed_domain)) {
$request->attributes->set('store', $store);
return $next($request);
}

// 5. Extraer el dominio del Origin o Referer enviado por el navegador
$originHeader = $request->header('Origin') ?? $request->header('Referer');

if (!$originHeader) {
return response()->json(['error' => 'Petición no autorizada: Origen no especificado.'], 403);
}

// Extraer solo el host (ej. de "https://cliente.com/subpagina" extrae "cliente.com")
$requestHost = parse_url($originHeader, PHP_URL_HOST);
$allowedHost = parse_url($store->allowed_domain, PHP_URL_HOST) ?? $store->allowed_domain;

// Normalizar quitando 'www.' para comparaciones
$requestHost = preg_replace('/^www\./', '', strtolower($requestHost ?? ''));
$allowedHost = preg_replace('/^www\./', '', strtolower($allowedHost));

// 6. Validar coincidencia de dominios
if ($requestHost !== $allowedHost && $originHeader !== $store->allowed_domain) {
return response()->json([
'error' => 'El dominio desde el que intentas agendar no está autorizado para esta tienda.'
], 403);
}

// Compartir la tienda con los controladores para no repetir la consulta SQL
$request->attributes->set('store', $store);

return $next($request);
}
}
