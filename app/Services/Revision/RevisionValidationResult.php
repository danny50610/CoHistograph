<?php

namespace App\Services\Revision;

use Illuminate\Support\MessageBag;

class RevisionValidationResult
{
    /**
     * @var array<int, array<int, array{code:string,message:string,meta:array<string,mixed>}>>
     */
    private array $actionErrors = [];

    /**
     * @var array<int, string>
     */
    private array $generalErrors = [];

    public function addGeneralError(string $message): void
    {
        $this->generalErrors[] = $message;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function addActionError(int $order, string $code, string $message, array $meta = []): void
    {
        $this->actionErrors[$order][] = [
            'code' => $code,
            'message' => $message,
            'meta' => $meta,
        ];
    }

    public function hasAnyError(): bool
    {
        return $this->generalErrors !== [] || $this->actionErrors !== [];
    }

    public function isValid(): bool
    {
        return ! $this->hasAnyError();
    }

    /**
     * @return array<int, array<int, array{code:string,message:string,meta:array<string,mixed>}>>
     */
    public function actionErrors(): array
    {
        ksort($this->actionErrors);

        return $this->actionErrors;
    }

    /**
     * @return array<int, list<string>>
     */
    public function actionMessages(): array
    {
        $messages = [];

        foreach ($this->actionErrors() as $order => $errors) {
            $messages[$order] = array_map(
                static fn (array $error): string => $error['message'],
                $errors,
            );
        }

        return $messages;
    }

    /**
     * @return array<int, string>
     */
    public function generalErrors(): array
    {
        return $this->generalErrors;
    }

    public function summary(): string
    {
        if ($this->isValid()) {
            return '驗證通過';
        }

        return '提交失敗，請修正錯誤後再提交';
    }

    public function toMessageBag(): MessageBag
    {
        $bag = new MessageBag;

        foreach ($this->generalErrors as $index => $message) {
            $bag->add("general.{$index}", $message);
        }

        foreach ($this->actionMessages() as $order => $messages) {
            foreach ($messages as $index => $message) {
                $bag->add("actions.{$order}.{$index}", $message);
            }
        }

        return $bag;
    }
}
