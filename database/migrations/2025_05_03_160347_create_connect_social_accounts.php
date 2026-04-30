<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectSocialAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connected_social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores');

            // Enum платформ
            $table->enum('platform', ['facebook', 'instagram', 'whatsapp']);

            // Facebook
            $table->string('page_id')->nullable(); // Page ID или phone_number_id для WhatsApp
            $table->string('page_name')->nullable();

            // Instagram
            $table->string('ig_business_id')->nullable();

            // WhatsApp
            $table->string('wa_business_account_id')->nullable(); // Если нужно хранить Business Account ID

            // Токен доступа
            $table->text('access_token')->nullable();

            // Возможности вебхука (например, ["messages"])
            $table->json('webhook_features')->nullable();

            // Флаг подписки
            $table->boolean('subscribed')->default(false);

            // Когда подключено
            $table->timestamp('connected_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('connected_social_accounts');
    }
}
