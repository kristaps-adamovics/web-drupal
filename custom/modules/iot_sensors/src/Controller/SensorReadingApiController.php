<?php

namespace Drupal\iot_sensors\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class SensorReadingApiController extends ControllerBase {

  protected $database;
  protected $time;

  public function __construct(Connection $database, TimeInterface $time) {
    $this->database = $database;
    $this->time = $time;
  }

  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('database'),
      $container->get('datetime.time'),
    );
  }

  public function receive(Request $request): JsonResponse {
    $payload = json_decode($request->getContent(), TRUE);

    if (!is_array($payload)) {
      return $this->errorResponse('Request body must be valid JSON.', 400);
    }

    $sensor_id = $payload['sensor'] ?? NULL;
    $value = $payload['value'] ?? NULL;

    if (!$this->isPositiveInteger($sensor_id)) {
      return $this->errorResponse('Field "sensor" must be an existing numeric sensor ID.', 400);
    }

    if (!is_numeric($value)) {
      return $this->errorResponse('Field "value" must be numeric.', 400);
    }

    $sensor = $this->database->select('iot_sensors_sensors', 's')
      ->fields('s', ['id', 'sensor_code', 'metric', 'unit'])
      ->condition('id', (int) $sensor_id)
      ->execute()
      ->fetchAssoc();

    if (!$sensor) {
      return $this->errorResponse('Sensor not found.', 404);
    }

    $created = $this->time->getRequestTime();
    $reading_id = $this->database->insert('iot_sensors_readings')
      ->fields([
        'sensor_id' => (int) $sensor_id,
        'value' => round((float) $value, 2),
        'created' => $created,
      ])
      ->execute();

    return new JsonResponse([
      'status' => 'ok',
      'reading_id' => (int) $reading_id,
      'sensor' => [
        'id' => (int) $sensor['id'],
        'code' => $sensor['sensor_code'],
        'metric' => $sensor['metric'],
        'unit' => $sensor['unit'],
      ],
      'value' => round((float) $value, 2),
      'created' => $created,
    ], 201);
  }

  private function isPositiveInteger($value): bool {
    return is_numeric($value) && (string) (int) $value === (string) $value && (int) $value > 0;
  }

  private function errorResponse(string $message, int $status): JsonResponse {
    return new JsonResponse([
      'status' => 'error',
      'message' => $message,
    ], $status);
  }

}
