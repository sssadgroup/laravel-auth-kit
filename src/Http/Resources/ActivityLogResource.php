<?php

namespace S3Tech\AuthKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \S3Tech\AuthKit\Models\ActivityLog
 */
class ActivityLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'user_id'      => $this->user_id,
            'event'        => $this->event,
            'action'       => $this->action,
            'subject_type' => $this->subject_type,
            'subject_id'   => $this->subject_id,
            'ip_address'   => $this->ip_address,
            'device_type'  => $this->device_type,
            'device_name'  => $this->device_name,
            'browser'      => $this->browser,
            'os'           => $this->os,
            'user_agent'   => $this->user_agent,
            'country'      => $this->country,
            'country_code' => $this->country_code,
            'region'       => $this->region,
            'city'         => $this->city,
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'metadata'     => $this->metadata,
            'created_at'   => $this->created_at,
            'user'         => $this->whenLoaded('user'),
            'subject'      => $this->whenLoaded('subject', function (): ?array {
                if ($this->subject === null) {
                    return null;
                }

                return [
                    'type'  => $this->subject->getMorphClass(),
                    'id'    => $this->subject->getKey(),
                    'label' => $this->resolveSubjectLabel(),
                ];
            }),
        ];
    }

    private function resolveSubjectLabel(): ?string
    {
        if ($this->subject === null) {
            return null;
        }

        foreach (['name', 'title', 'label', 'reference', 'code', 'number'] as $attribute) {
            $value = data_get($this->subject, $attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        $firstName = data_get($this->subject, 'first_name');
        $lastName  = data_get($this->subject, 'last_name');
        $fullName  = trim(collect([$firstName, $lastName])->filter()->implode(' '));

        if ($fullName !== '') {
            return $fullName;
        }

        $email = data_get($this->subject, 'email');

        if (filled($email)) {
            return (string) $email;
        }

        return class_basename($this->subject) . '#' . $this->subject->getKey();
    }
}
