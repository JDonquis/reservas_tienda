<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected function getClient(Store $store): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        if ($store->google_access_token) {
            $client->setAccessToken(json_decode($store->google_access_token, true));

            if ($client->isAccessTokenExpired() && $store->google_refresh_token) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($store->google_refresh_token);
                $store->update([
                    'google_access_token' => json_encode($newToken)
                ]);
            }
        }

        return $client;
    }

    public function createAppointmentEvent(Store $store, array $appointmentData): ?Event
    {
        if (!$store->google_access_token) {
            return null;
        }

        $client = $this->getClient($store);
        $service = new Calendar($client);

        $event = new Event([
            'summary' => 'Reserva: ' . $appointmentData['customer_name'],
            'description' => 'Teléfono: ' . $appointmentData['customer_phone'] . "\nEmail: " . $appointmentData['customer_email'],
            'start' => [
                'dateTime' => Carbon::parse($appointmentData['start_time'])->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => Carbon::parse($appointmentData['end_time'])->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'attendees' => [
                ['email' => $appointmentData['customer_email']],
            ],
        ]);

        $calendarId = $store->google_calendar_id ?? 'primary';
        return $service->events->insert($calendarId, $event);
    }

    public function deleteAppointmentEvent(Store $store, string $googleEventId): bool
    {
        if (!$store->google_refresh_token && !$store->google_access_token) {
            return false;
        }

        $client = $this->getClient($store);
        $service = new Calendar($client);

        try {
            $calendarId = $store->google_calendar_id ?? 'primary';
            $service->events->delete($calendarId, $googleEventId);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Calendar Delete Error: ' . $e->getMessage());
            return false;
        }
    }
}
