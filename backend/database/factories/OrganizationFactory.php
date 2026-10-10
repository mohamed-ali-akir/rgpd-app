<?php

namespace Database\Factories;

use App\Enums\OrganizationCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $domain = fake()->unique()->domainWord().'.test';

        return [
            'name' => fake()->company(),
            'domain' => $domain,
            'category' => OrganizationCategory::Directory,
            'privacy_email' => 'dpo@'.$domain, // domaine .test : n'existe pas, uniquement pour les tests
            'privacy_form_url' => null,
            'dpo_name' => null,
            'verified_at' => now(),
        ];
    }

    public function category(OrganizationCategory $category): static
    {
        return $this->state(['category' => $category]);
    }

    public function withoutEmail(): static
    {
        return $this->state(['privacy_email' => null]);
    }
}
