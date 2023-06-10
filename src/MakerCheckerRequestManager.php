<?php

namespace Prismaticode\MakerChecker;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Prismaticode\MakerChecker\Contracts\MakerCheckerRequestInterface;
use Prismaticode\MakerChecker\Enums\Hooks;
use Prismaticode\MakerChecker\Enums\RequestStatuses;
use Prismaticode\MakerChecker\Enums\RequestTypes;
use Prismaticode\MakerChecker\Events\RequestApproved;
use Prismaticode\MakerChecker\Events\RequestFailed;
use Prismaticode\MakerChecker\Events\RequestInitiated;
use Prismaticode\MakerChecker\Events\RequestRejected;
use Prismaticode\MakerChecker\Exceptions\InvalidRequestTypePassed;
use Prismaticode\MakerChecker\Exceptions\ModelCannotCheckRequests;
use Prismaticode\MakerChecker\Exceptions\RequestCannotBeChecked;

class MakerCheckerRequestManager
{
    private Application $app;

    private array $configData;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->configData = $app['config']['makerchecker'];
    }

    /**
     * Begin initiating a new request.
     *
     * @return \Prismaticode\MakerChecker\RequestBuilder
     */
    public function request(): RequestBuilder
    {
        return $this->app[RequestBuilder::class];
    }

    /**
     * Define a callback to be executed after any request is initiated.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function afterInitiating(Closure $callback): void
    {
        $this->app['events']->listen(RequestInitiated::class, $callback);
    }

    /**
     * Define a callback to be executed after any request is fulfilled.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function afterApproving(Closure $callback): void
    {
        $this->app['events']->listen(RequestApproved::class, $callback);
    }

    /**
     * Define a callback to be executed after any request is rejected.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function afterRejecting(Closure $callback): void
    {
        $this->app['events']->listen(RequestRejected::class, $callback);
    }

    /**
     * Define a callback to be executed in the event of a failure while fulfilling the request.
     *
     * @param \Closure $callback
     *
     * @return void
     */
    public function onFailure(Closure $callback): void
    {
        $this->app['events']->listen(RequestFailed::class, $callback);
    }

    /**
     * Approve a pending maker-checker request.
     *
     * @param \Prismaticode\MakerChecker\Contracts\MakerCheckerRequestInterface $request
     * @param \Illuminate\Database\Eloquent\Model $approver
     * @param string|null $remarks
     *
     * @return \Prismaticode\MakerChecker\Contracts\MakerCheckerRequestInterface
     */
    public function approve(MakerCheckerRequestInterface $request, Model $approver, ?string $remarks = null): MakerCheckerRequestInterface
    {
        if (! $request instanceof Model) {
            throw new Exception('Request class must extend the base Eloquent model class.');
        }

        $this->assertRequestCanBeChecked($request, $approver);

        $request->update(['status' => RequestStatuses::PROCESSING]);

        try {
            $this->executeCallbackHook($request, Hooks::PRE_APPROVAL);

            //TODO: put the below in a job instead. This will be useful when it comes to executing generic actions.
            DB::beginTransaction();

            $this->fulfillRequest($request);

            $request->update([
                'status' => RequestStatuses::APPROVED,
                'checker_type' => $approver->getMorphClass(),
                'checker_id' => $approver->getKey(),
                'checked_at' => Carbon::now(),
                'remarks' => $remarks,
            ]);

            DB::commit();

            $this->executeCallbackHook($request, Hooks::POST_APPROVAL); //TODO: do this inside a job instead.

            $this->app['events']->dispatch(new RequestApproved($request));
        } catch (\Throwable $e) {
            DB::rollBack();

            $request->update([
                'status' => RequestStatuses::FAILED,
                'exception' => $e->getTraceAsString(),
                'checker_type' => $approver->getMorphClass(),
                'checker_id' => $approver->getKey(),
                'checked_at' => Carbon::now(),
                'remarks' => $remarks,
            ]);

            $onFailureCallBack = $this->getHook($request, Hooks::ON_FAILURE);

            if ($onFailureCallBack) {
                $onFailureCallBack($request, $e);
            }

            $this->app['events']->dispatch(new RequestFailed($request, $e));
        } finally {
            return $request;
        }
    }

    /**
     * Reject a pending maker-checker request.
     *
     * @param \Prismaticode\MakerChecker\Contracts\MakerCheckerRequestInterface $request
     * @param \Illuminate\Database\Eloquent\Model $rejector
     * @param string|null $remarks
     *
     * @return \Prismaticode\MakerChecker\Contracts\MakerCheckerRequestInterface
     */
    public function reject(MakerCheckerRequestInterface $request, Model $rejector, ?string $remarks = null): MakerCheckerRequestInterface
    {
        if (! $request instanceof Model) {
            throw new Exception('Request class must extend the base Eloquent model class.');
        }

        $this->assertRequestCanBeChecked($request, $rejector);

        $request->update(['status' => RequestStatuses::PROCESSING]);

        try {
            $this->executeCallbackHook($request, Hooks::PRE_REJECTION);

            //TODO: put the below in a job instead. This will be useful when it comes to executing generic actions.

            $request->update([
                'status' => RequestStatuses::REJECTED,
                'checker_type' => $rejector->getMorphClass(),
                'checker_id' => $rejector->getKey(),
                'checked_at' => Carbon::now(),
                'remarks' => $remarks,
            ]);

            $this->executeCallbackHook($request, Hooks::POST_REJECTION); //TODO: do this inside a job instead.

            $this->app['events']->dispatch(new RequestRejected($request));

            return $request;
        } catch (\Throwable $e) {
            $request->update([
                'status' => RequestStatuses::FAILED,
                'exception' => $e->getTraceAsString(),
                'checker_type' => $rejector->getMorphClass(),
                'checker_id' => $rejector->getKey(),
                'checked_at' => Carbon::now(),
                'remarks' => $remarks,
            ]);

            $onFailureCallBack = $this->getHook($request, Hooks::ON_FAILURE);

            if ($onFailureCallBack) {
                $onFailureCallBack($request, $e);
            }

            $this->app['events']->dispatch(new RequestFailed($request, $e));
        }
    }

    private function assertRequestCanBeChecked(MakerCheckerRequestInterface $request, Model $checker): void
    {
        $this->assertModelCanCheckRequests($checker);

        if (! $request->isOfStatus(RequestStatuses::PENDING)) {
            throw RequestCannotBeChecked::create('Cannot act on a non-pending request.');
        }

        $requestExpirationInMinutes = data_get($this->configData, 'request_expiration_in_minutes');

        if ($requestExpirationInMinutes && Carbon::now()->diffInMinutes($request->created_at) > $requestExpirationInMinutes) {
            throw RequestCannotBeChecked::create('Expired request.');
        }

        if ($checker->is($request->maker)) {
            throw RequestCannotBeChecked::create('Request checker cannot be the same as the maker.');
        }
    }

    private function executeCallbackHook(MakerCheckerRequestInterface $request, string $hook): void
    {
        $callback = $this->getHook($request, $hook);

        if ($callback) {
            $callback($request);
        }
    }

    private function getHook(MakerCheckerRequestInterface $request, string $hookName): ?Closure
    {
        $hooks = data_get($request->metadata, 'hooks', []);

        $serializedClosure = data_get($hooks, $hookName);

        return $serializedClosure ? unserialize($serializedClosure)->getClosure() : null;
    }

    private function fulfillRequest(MakerCheckerRequestInterface $request): void
    {
        if ($request->isOfType(RequestTypes::CREATE)) {
            $subjectClass = $request->subject_type;

            $subjectClass::create($request->payload);
        } elseif ($request->isOfType(RequestTypes::UPDATE)) {
            $request->subject->update($request->payload);
        } elseif ($request->isOfType(RequestTypes::DELETE)) {
            $request->subject->delete();
        } else {
            throw InvalidRequestTypePassed::create($request->type);
        }
    }

    private function assertModelCanCheckRequests(Model $checker): void
    {
        $checkerModel = get_class($checker);
        $allowedCheckers = data_get($this->configData, 'whitelisted_models.checker');

        if (is_string($allowedCheckers)) {
            $allowedCheckers = [$allowedCheckers];
        }

        if (! is_array($allowedCheckers)) {
            $allowedCheckers = [];
        }

        if(! empty($allowedCheckers) && ! in_array($checkerModel, $allowedCheckers)) {
            throw ModelCannotCheckRequests::create($checkerModel);
        }
    }
}
