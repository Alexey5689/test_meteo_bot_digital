<?php

$token = "8041571009:AAGC-lIx-pDlZNdK4mE_6WnfqiAcKGnQi2Y";
$apiUrl = "https://api.telegram.org/bot{$token}/";

// Для Long Polling нужно хранить последний обработанный update_id
$lastUpdateId = 0;

while (true) {
    // Получаем обновления
    $updates = json_decode(file_get_contents($apiUrl . "getUpdates?offset=" . ($lastUpdateId + 1)), true);
    
    if (isset($updates['result']) && count($updates['result']) > 0) {
        foreach ($updates['result'] as $update) {
            $lastUpdateId = $update['update_id'];
            
            if (isset($update['message'])) {
                $chatId = $update['message']['chat']['id'];
                $text = $update['message']['text'];

                if ($text === '/start') {
                    showCities($chatId, $apiUrl);
                } elseif (in_array($text, getCitiesList())) {
                    sendWeather($chatId, $text, $apiUrl);
                } else {
                    $response = "⛔ Неизвестная команда. Нажмите /start";
                    sendMessage($chatId, $response, $apiUrl);
                }
            }
        }
    }
    sleep(1);
}

function sendMessage($chatId, $text, $apiUrl) {
    file_get_contents($apiUrl . "sendMessage?chat_id={$chatId}&text=" . urlencode($text));
}

function getCitiesList() {
    return [
        'Москва', 'Берлин', 'Токио', 
        'Нью-Йорк', 'Париж', 'Лондон',
        'Пекин', 'Дубай', 'Сидней', 'Рим'
    ];
}

function showCities($chatId, $apiUrl) {
    $cities = getCitiesList();
    
    // Формируем текст сообщения с нумерованным списком городов
    $response = "🌍 Выберите город:\n\n";
    foreach ($cities as $index => $city) {
        $response .= ($index + 1) . ". $city\n";
    }
    
    // Создаем клавиатуру с городами (каждый город в отдельном ряду)
    $keyboard = array_map(function($city) {
        return [$city]; // Каждый город в отдельном массиве = отдельный ряд
    }, $cities);
    
    $replyMarkup = json_encode([
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ]);
    
    // Отправляем сообщение с клавиатурой
    file_get_contents($apiUrl . "sendMessage?chat_id={$chatId}&text=" . urlencode($response) . "&reply_markup={$replyMarkup}");
}

function sendWeather($chatId, $city, $apiUrl) {
    $cityData = [
    'Москва' => [
        'lat' => 55.7558, 
        'lon' => 37.6176, 
        'timezone' => 'Europe/Moscow'
    ],
    'Берлин' => [
        'lat' => 52.5200, 
        'lon' => 13.4050, 
        'timezone' => 'Europe/Berlin'
    ],
    'Токио' => [
        'lat' => 35.6762, 
        'lon' => 139.6503,
        'timezone' => 'Asia/Tokyo'
    ],
    'Нью-Йорк' => [
        'lat' => 40.7128, 
        'lon' => -74.0060,
        'timezone' => 'America/New_York'
    ],
    'Париж' => [
        'lat' => 48.8566, 
        'lon' => 2.3522, 
        'timezone' => 'Europe/Paris'
    ],
    'Лондон' => [
        'lat' => 51.5074, 
        'lon' => -0.1278, 
        'timezone' => 'Europe/London'
    ],
    'Пекин' => [
        'lat' => 39.9042, 
        'lon' => 116.4074, 
        'timezone' => 'Asia/Shanghai'
    ],
    'Дубай' => [
        'lat' => 25.2048, 
        'lon' => 55.2708,
        'timezone' => 'Asia/Dubai'
    ],
    'Сидней' => [
        'lat' => -33.8688, 
        'lon' => 151.2093,
        'timezone' => 'Australia/Sydney'
    ],
    'Рим' => [
        'lat' => 41.9028, 
        'lon' => 12.4964,
        'timezone' => 'Europe/Rome'
    ]
];

    if (!isset($cityData[$city])) {
        sendMessage($chatId, "Город не найден.", $apiUrl);
        return;
    }

    $lat = $cityData[$city]['lat'];
    $lon = $cityData[$city]['lon'];
    $timezone = $cityData[$city]['timezone'];

    $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true&hourly=temperature_2m,relativehumidity_2m,pressure_msl,precipitation&timezone={$timezone}";
    $weatherData = json_decode(file_get_contents($weatherUrl), true);

    if (!$weatherData) {
        sendMessage($chatId, "Ошибка при получении погоды.", $apiUrl);
        return;
    }

    $current = $weatherData['current_weather'];
    $dateTime = new DateTime($current['time'], new DateTimeZone($timezone));
    $time = $dateTime->format('H:i');
    $temp = $current['temperature'];
    $humidity = $weatherData['hourly']['relativehumidity_2m'][0];
    $pressure = $weatherData['hourly']['pressure_msl'][0];
    $precipitation = $weatherData['hourly']['precipitation'][0];
    $precipitationText = ($precipitation > 0.1) ? "Да 🌧️ ($precipitation мм)" : "Нет ☀️";

    $response = "🌤️ Погода в {$city}:\n";
    $response .= "🕒 Время: {$time}\n";
    $response .= "🌡️ Температура: {$temp}°C\n";
    $response .= "💧 Влажность: {$humidity}%\n";
    $response .= "📊 Давление: {$pressure} hPa\n";
    $response .= "🌧️ Осадки: $precipitationText";

    sendMessage($chatId, $response, $apiUrl);
}