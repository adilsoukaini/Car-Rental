<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Core\Support\HasMinimumRole;
use App\Enums\Role;
use App\Services\PushNotificationService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Admin-only page to compose and broadcast a push notification to every
 * registered device (announcements). Minimal by design — a title + body + Send
 * button. Delivery is delegated entirely to PushNotificationService::sendToAll()
 * (the same service the booking-event listeners use), so there's exactly one
 * implementation of the Expo HTTP call.
 *
 * @property-read Schema $form
 */
class SendPushNotification extends Page
{
    use HasMinimumRole;

    protected static function minimumRole(): Role
    {
        return Role::Admin;
    }

    protected static ?string $title = 'Send Push Notification';

    protected static ?string $slug = 'send-push-notification';

    protected string $view = 'filament.pages.send-push-notification';

    // ------------------------------------------------------------------
    // Filament v4 overrides — use getters rather than typed properties
    // because the base Page class declares properties with union types
    // (BackedEnum|string|null, etc.) that ?string doesn't satisfy.
    // ------------------------------------------------------------------

    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-bell-alert';
    }

    public static function getNavigationLabel(): string
    {
        return 'Send Push';
    }

    public static function getNavigationSort(): ?int
    {
        return 7;
    }

    // ------------------------------------------------------------------
    // Form
    // ------------------------------------------------------------------

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Shown in bold at the top of the notification.'),

                Textarea::make('body')
                    ->label('Body')
                    ->required()
                    ->maxLength(1000)
                    ->rows(4)
                    ->helperText('The notification message text.'),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $count = app(PushNotificationService::class)->sendToAll(
            (string) $data['title'],
            (string) $data['body'],
            ['type' => 'announcement'],
        );

        Notification::make()
            ->title(sprintf('Push sent to %d device(s)', $count))
            ->body($count === 0 ? 'No registered devices.' : '')
            ->success()
            ->send();

        $this->form->fill();
    }
}
