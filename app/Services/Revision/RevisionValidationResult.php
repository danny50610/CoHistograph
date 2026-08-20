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
     * @var array<int, array<int, array{code:string,message:string,meta:array<string,mixed>}>>
     */
    private array $actionWarnings = [];

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

    /**
     * @param  array<string, mixed>  $meta
     */
    public function addActionWarning(int $order, string $code, string $message, array $meta = []): void
    {
        $this->actionWarnings[$order][] = [
            'code' => $code,
            'message' => $message,
            'meta' => $meta,
        ];
    }

    public function hasAnyError(): bool
    {
        return $this->generalErrors !== [] || $this->actionErrors !== [];
    }

    public function hasAnyWarning(): bool
    {
        return $this->actionWarnings !== [];
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
     * @return array<int, array<int, array{code:string,message:string,meta:array<string,mixed>}>>
     */
    public function actionWarnings(): array
    {
        ksort($this->actionWarnings);

        return $this->actionWarnings;
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
     * @return array<int, list<string>>
     */
    public function actionWarningMessages(): array
    {
        $messages = [];

        foreach ($this->actionWarnings() as $order => $warnings) {
            $messages[$order] = array_map(
                static fn (array $warning): string => $warning['message'],
                $warnings,
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
        if (! $this->isValid()) {
            return '提交失敗，請修正錯誤後再提交';
        }

        if ($this->hasAnyWarning()) {
            return '驗證通過（有警告）';
        }

        return '驗證通過';
    }

    public function checkSummary(): string
    {
        if (! $this->isValid()) {
            return '檢查未通過，請修正錯誤後再繼續';
        }

        if ($this->hasAnyWarning()) {
            return '檢查通過（有警告）';
        }

        return '檢查通過';
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
