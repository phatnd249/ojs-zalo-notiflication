<?php

class ZaloPhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', trim($phone));

        if (strpos($clean, '0084') === 0) {
            $clean = substr($clean, 2);
        } elseif (strpos($clean, '0') === 0) {
            $clean = '84' . substr($clean, 1);
        }

        if (!preg_match('/^84[0-9]{9}$/', $clean)) {
            return '';
        }

        return $clean;
    }

    public static function getPhonesForEvent(string $eventType, array $recipientGroups): array
    {
        $phoneNumbers = [];
        $acceptedEvents = self::getAcceptedEvents($eventType);

        foreach ($recipientGroups as $group) {
            $events = isset($group['events']) && is_array($group['events']) ? $group['events'] : [];
            if (empty(array_intersect($acceptedEvents, $events))) {
                continue;
            }

            $phones = isset($group['phones'])
                ? (is_array($group['phones']) ? $group['phones'] : explode(',', $group['phones']))
                : [];

            foreach ($phones as $phone) {
                $clean = self::normalize($phone);
                if (!empty($clean)) {
                    $phoneNumbers[] = $clean;
                }
            }
        }

        return array_values(array_unique($phoneNumbers));
    }

    public static function getAcceptedEvents(string $eventType): array
    {
        $eventAliases = [
            'REVIEW_REQUEST' => ['REVIEW_REQUEST', 'REVIEW_REMINDER'],
            'REVIEW_RESPONSE' => ['REVIEW_RESPONSE', 'REVIEW_REQUEST', 'REVIEW_REMINDER'],
            'REVIEW_COMPLETED' => ['REVIEW_COMPLETED', 'REVIEW_REMINDER'],
        ];

        return $eventAliases[$eventType] ?? [$eventType];
    }
}
