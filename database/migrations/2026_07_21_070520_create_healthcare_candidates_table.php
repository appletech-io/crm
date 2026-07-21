<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('healthcare_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('previous_surname')->nullable();

            $table->string('right_to_work_type')->nullable();
            $table->string('visa_share_code')->nullable();
            $table->date('visa_issue_date')->nullable();
            $table->date('visa_expiry_date')->nullable();
            $table->text('visa_notes')->nullable();

            $table->string('has_dbs')->nullable();
            $table->string('has_naric')->nullable();
            $table->string('dbs_certificate_number')->nullable();
            $table->string('update_service_response')->nullable();
            $table->timestamp('update_service_checked_at')->nullable();
            $table->date('dbs_checked_date')->nullable();

            $table->string('proof_of_address_match')->nullable();
            $table->text('proof_of_address_extracted')->nullable();
            $table->timestamp('proof_of_address_checked_at')->nullable();
            $table->string('ni_number_match')->nullable();
            $table->string('ni_number_extracted')->nullable();
            $table->timestamp('ni_number_checked_at')->nullable();

            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();

            $table->text('address')->nullable();
            $table->string('postcode')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('city')->nullable();
            $table->string('county')->nullable();
            $table->string('country')->nullable();

            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('mobile')->nullable();
            $table->string('ni_number')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_number')->nullable();
            $table->string('has_health_condition_or_disability')->nullable();
            $table->text('health_condition_details')->nullable();
            $table->text('reasonable_accommodations')->nullable();

            $table->string('retired_early')->nullable();
            $table->string('retired_early_medical_grounds')->nullable();
            $table->string('dismissed_from_relevant_position')->nullable();
            $table->text('dismissal_details')->nullable();
            $table->string('subject_to_disciplinary_action')->nullable();
            $table->text('disciplinary_action_details')->nullable();
            $table->string('lived_overseas_six_months')->nullable();
            $table->text('overseas_details')->nullable();
            $table->string('unspent_convictions')->nullable();
            $table->text('unspent_convictions_details')->nullable();
            $table->string('spent_convictions_not_protected')->nullable();

            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('notes')->nullable();
            $table->longText('education_and_qualification')->nullable();
            $table->longText('employment_history')->nullable();
            $table->foreignId('qualification_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('compliance_step')->nullable();
            $table->timestamp('compliance_completed_at')->nullable();
            $table->foreignId('compliance_completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('professional_registration_body')->nullable();
            $table->string('professional_registration_number')->nullable();
            $table->date('professional_registration_checked_at')->nullable();

            $table->json('availability')->nullable();
            $table->date('available_from')->nullable();
            $table->json('care_settings')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('healthcare_candidates');
    }
};
