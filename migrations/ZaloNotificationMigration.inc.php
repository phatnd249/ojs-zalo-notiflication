<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

/** Tạo bảng outbox phục vụ gửi Zalo bất đồng bộ. */
class ZaloNotificationMigration extends Migration
{
    public function up()
    {
        if (Capsule::schema()->hasTable('zalo_notification_outbox')) {
            return;
        }

        Capsule::schema()->create('zalo_notification_outbox', function (Blueprint $table) {
            $table->bigIncrements('outbox_id');
            $table->bigInteger('context_id')->default(0);
            $table->string('event_type', 64)->default('DIRECT');
            $table->string('dedupe_key', 64);
            $table->longText('message_text');
            $table->longText('recipients_json');
            $table->string('status', 20)->default('pending');
            $table->smallInteger('attempts')->default(0);
            $table->dateTime('available_at');
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');

            $table->unique(['dedupe_key'], 'zalo_outbox_dedupe_key');
            $table->index(['status', 'available_at'], 'zalo_outbox_ready');
            $table->index(['context_id'], 'zalo_outbox_context');
        });
    }
}
