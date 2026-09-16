<?php

declare(strict_types=1);

namespace MosChat\Channels;

interface ChannelInterface
{
    public function key(): string;

    /**
     * Normalize an inbound channel payload into a message shape.
     *
     * @return array{
     *   body: string,
     *   message_type: string,
     *   sender_type: string,
     *   metadata: array<string, mixed>
     * }
     */
    public function receiveMessage(array $payload): array;

    /**
     * Deliver an outbound message on this channel.
     *
     * @return array{success: bool, external_id: ?string, error: ?string}
     */
    public function sendMessage(array $conversation, array $message): array;

    /**
     * @return array{
     *   name: ?string,
     *   email: ?string,
     *   phone: ?string,
     *   external_id: ?string,
     *   attributes: array<string, mixed>
     * }
     */
    public function normalizeVisitor(array $payload): array;

    /**
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    public function createConversation(int $companyId, array $ctx): array;
}
