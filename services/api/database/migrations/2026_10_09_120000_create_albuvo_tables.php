<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('albums', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('owner_id')->constrained('users');
            $t->string('slug')->unique(); $t->string('title',100); $t->text('description')->nullable();
            $t->string('visibility')->default('private'); $t->boolean('allow_guest_upload')->default(false);
            $t->boolean('allow_member_download')->default(true); $t->boolean('require_upload_approval')->default(true);
            $t->string('status')->default('active'); $t->unsignedBigInteger('used_bytes')->default(0);
            $t->unsignedBigInteger('reserved_bytes')->default(0); $t->unsignedBigInteger('quota_bytes')->default(1073741824);
            $t->timestamps();
        });
        Schema::create('album_members', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $t->string('role'); $t->string('state')->default('active'); $t->timestamps();
            $t->unique(['album_id','user_id']);
        });
        Schema::create('album_invites', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('created_by')->constrained('users'); $t->string('token_hash',64)->unique();
            $t->string('scope')->default('upload'); $t->unsignedInteger('used_count')->default(0);
            $t->unsignedInteger('max_uses')->nullable(); $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable(); $t->timestamps();
        });
        Schema::create('guest_contributors', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('invite_id')->constrained('album_invites')->cascadeOnDelete();
            $t->string('display_name',80); $t->string('secret_hash',64)->unique();
            $t->string('consent_version',24); $t->timestamps();
        });
        Schema::create('media', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('user_id')->nullable()->constrained('users');
            $t->foreignUuid('guest_contributor_id')->nullable()->constrained();
            $t->string('status')->default('initiated'); $t->string('original_filename');
            $t->string('mime_detected')->nullable(); $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable(); $t->unsignedBigInteger('byte_size')->default(0);
            $t->timestamp('published_at')->nullable(); $t->foreignUuid('moderated_by')->nullable()->constrained('users');
            $t->text('moderation_reason')->nullable(); $t->timestamps();
            $t->index(['album_id','status','created_at']);
        });
        Schema::create('media_objects', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $t->string('kind'); $t->string('object_key')->unique(); $t->string('mime');
            $t->unsignedBigInteger('byte_size'); $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable(); $t->timestamps();
        });
        Schema::create('upload_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('album_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $t->foreignUuid('user_id')->nullable()->constrained('users');
            $t->foreignUuid('guest_contributor_id')->nullable()->constrained();
            $t->string('object_key')->unique(); $t->string('idempotency_key',100);
            $t->unsignedBigInteger('declared_size'); $t->unsignedBigInteger('reserved_bytes');
            $t->string('declared_mime'); $t->string('status')->default('initiated');
            $t->timestamp('expires_at'); $t->timestamp('finalized_at')->nullable(); $t->timestamps();
            $t->unique(['album_id','idempotency_key']);
        });
        Schema::create('moderation_actions', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('media_id')->constrained('media')->cascadeOnDelete();
            $t->foreignUuid('actor_id')->constrained('users'); $t->string('decision');
            $t->text('reason')->nullable(); $t->timestamps();
        });
        Schema::create('reports', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('media_id')->nullable()->constrained('media');
            $t->foreignUuid('album_id')->constrained('albums'); $t->foreignUuid('user_id')->nullable()->constrained('users');
            $t->foreignUuid('guest_contributor_id')->nullable()->constrained(); $t->string('reason');
            $t->text('description')->nullable(); $t->string('contact_email')->nullable();
            $t->string('status')->default('open'); $t->timestamps();
        });
        Schema::create('consent_acceptances', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->foreignUuid('user_id')->nullable()->constrained('users');
            $t->foreignUuid('guest_contributor_id')->nullable()->constrained();
            $t->string('document_type'); $t->string('version'); $t->timestamp('accepted_at');
        });
    }
    public function down(): void {
        foreach (['consent_acceptances','reports','moderation_actions','upload_sessions','media_objects','media','guest_contributors','album_invites','album_members','albums'] as $name) Schema::dropIfExists($name);
    }
};