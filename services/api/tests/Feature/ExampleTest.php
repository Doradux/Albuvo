<?php
namespace Tests\Feature;
use Tests\TestCase;
class ExampleTest extends TestCase {
    public function test_health_endpoint_is_accessible(): void {
        $this->getJson('/api/v1/health')->assertOk()->assertJson(['status'=>'ok']);
    }
}
