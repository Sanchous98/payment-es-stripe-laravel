<?php

namespace PaymentSystem\Laravel\Stripe\Serializer;

use EventSauce\EventSourcing\AggregateRootId;
use PaymentSystem\Gateway\Resources\PaymentIntentInterface;
use PaymentSystem\Laravel\Stripe\Gateway\PaymentIntent;
use PaymentSystem\Laravel\Uuid;
use Stripe\BalanceTransaction;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PaymentIntentNormalizer implements DenormalizerInterface, NormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        assert($data instanceof PaymentIntent);

        return [
            'account_id' => $data->accountId->toString(),
            'payment_intent' => $data->paymentIntent->toArray(),
            'payment_method_id' => $data->paymentMethodId === null ? null : $this->normalizer->normalize($data->paymentMethodId, $format, $context),
            'balance_transactions' => array_map(fn(BalanceTransaction $tx) => $tx->toArray(), $data->transactions),
        ];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof PaymentIntent;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): PaymentIntent
    {
        return new PaymentIntent(
            Uuid::fromString($data['account_id']),
            \Stripe\PaymentIntent::constructFrom($data['payment_intent']),
            $data['payment_method_id'] === null ? null : $this->denormalizer->denormalize($data['payment_method_id'], AggregateRootId::class, $format, $context),
            ...array_map(fn(array $tx) => BalanceTransaction::constructFrom($tx) ,$data['balance_transactions']),
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, PaymentIntentInterface::class, true)
            && isset($data['payment_intent']['id'])
            && str_starts_with($data['payment_intent']['id'], 'pi_');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PaymentIntentInterface::class => false,
            PaymentIntent::class => true,
        ];
    }
}