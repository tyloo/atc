<?php

declare(strict_types=1);

namespace Tyloo\Atc\Tests\Fixtures\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tyloo\Atc\Tests\Fixtures\Entity\Item;
use Tyloo\Atc\Tests\Fixtures\Message\SendWelcomeEmail;
use Tyloo\Atc\Tests\Fixtures\Service\Greeter;

/**
 * Fixture controller exposing the routes that the InteractsWith* trait
 * tests exercise: ping, list/create/echo for users, cache, mailer,
 * messenger, notifier, database and an HttpClient pass-through.
 */
final class JsonController
{
    /**
     * Decode the request JSON body into a typed associative array, falling back to []
     * when the body is empty or non-array. This narrows mixed types for the analyzer.
     *
     * @return array<array-key, mixed>
     */
    private static function decodePayload(Request $request): array
    {
        // json_decode is `mixed` by signature; the is_array() guard below narrows it.
        // @mago-ignore analysis:mixed-assignment
        $decoded = json_decode($request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function stringValue(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    #[Route('/ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                ['id' => 1, 'email' => 'alice@example.com'],
                ['id' => 2, 'email' => 'bob@example.com'],
            ],
        ]);
    }

    #[Route('/users', methods: ['POST'])]
    public function createUser(Request $request): JsonResponse
    {
        $payload = self::decodePayload($request);

        return new JsonResponse(
            ['id' => 42, 'email' => $payload['email'] ?? null],
            Response::HTTP_CREATED,
            ['Location' => '/users/42'],
        );
    }

    #[Route('/echo-headers', methods: ['GET'])]
    public function echoHeaders(Request $request): JsonResponse
    {
        return new JsonResponse([
            'authorization' => $request->headers->get('Authorization'),
            'x_custom' => $request->headers->get('X-Custom'),
        ]);
    }

    #[Route('/forbidden', methods: ['GET'])]
    public function forbidden(): JsonResponse
    {
        return new JsonResponse(['error' => 'forbidden'], 403);
    }

    #[Route('/users/{id}', methods: ['PATCH'])]
    public function patchUser(string $id, Request $request): JsonResponse
    {
        $payload = self::decodePayload($request);

        return new JsonResponse([
            'id' => $id,
            'method' => $request->getMethod(),
            'patched' => $payload,
        ]);
    }

    #[Route('/users/{id}', methods: ['PUT'])]
    public function putUser(string $id, Request $request): JsonResponse
    {
        $payload = self::decodePayload($request);

        return new JsonResponse([
            'id' => $id,
            'method' => $request->getMethod(),
            'replaced' => $payload,
        ]);
    }

    #[Route('/users/{id}', methods: ['DELETE'])]
    public function deleteUser(string $id, Request $request): JsonResponse
    {
        return new JsonResponse(
            ['id' => $id, 'method' => $request->getMethod()],
            Response::HTTP_NO_CONTENT,
        );
    }

    #[Route('/greet/{name}', methods: ['GET'])]
    public function greet(string $name, Greeter $greeter): JsonResponse
    {
        return new JsonResponse(['message' => $greeter->greet($name)]);
    }

    #[Route('/items', methods: ['POST'])]
    public function createItem(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $payload = self::decodePayload($request);
        $item = new Item(self::stringValue($payload['name'] ?? null, 'unnamed'));
        $em->persist($item);
        $em->flush();

        return new JsonResponse(['id' => $item->id, 'name' => $item->name], 201);
    }

    #[Route('/dispatch-welcome', methods: ['POST'])]
    public function dispatchWelcome(Request $request, MessageBusInterface $bus): JsonResponse
    {
        $payload = self::decodePayload($request);
        $bus->dispatch(new SendWelcomeEmail(self::stringValue($payload['email'] ?? null, 'unknown')));

        return new JsonResponse(['dispatched' => true]);
    }

    #[Route('/notify', methods: ['POST'])]
    public function notify(Request $request, NotifierInterface $notifier): JsonResponse
    {
        $payload = self::decodePayload($request);
        $notifier->send(
            new Notification(self::stringValue($payload['subject'] ?? null, 'hi')),
            new Recipient(self::stringValue($payload['email'] ?? null, 'x@example.com')),
        );

        return new JsonResponse(['notified' => true]);
    }

    #[Route('/cache/{key}', methods: ['POST'])]
    public function setCache(string $key, Request $request, CacheInterface $cache): JsonResponse
    {
        $payload = self::decodePayload($request);
        // Test fixture deliberately stores arbitrary JSON values into the cache;
        // narrowing here would hide the mixed-cache scenario the test exercises.
        // @mago-ignore analysis:mixed-assignment
        $value = $payload['value'] ?? null;
        $cache->get($key, static function (ItemInterface $item) use ($value): mixed {
            $item->set($value);

            return $value;
        });

        return new JsonResponse(['stored' => $value]);
    }

    #[Route('/external', methods: ['GET'])]
    public function external(HttpClientInterface $httpClient): JsonResponse
    {
        $response = $httpClient->request('GET', 'https://example.invalid/data');

        return new JsonResponse([
            'status' => $response->getStatusCode(),
            'body' => $response->toArray(false),
        ]);
    }

    #[Route('/send-email', methods: ['POST'])]
    public function sendEmail(Request $request, MailerInterface $mailer): JsonResponse
    {
        $payload = self::decodePayload($request);
        $mailer->send(
            (new Email())->from('noreply@example.com')
                ->to(self::stringValue($payload['to'] ?? null, 'unknown@example.com'))
                ->subject(self::stringValue($payload['subject'] ?? null, 'Hello'))
                ->text(self::stringValue($payload['body'] ?? null, 'Test')),
        );

        return new JsonResponse(['sent' => true]);
    }
}
