<?php

namespace PhpTek\Sentry\Middleware;

use PhpTek\Sentry\Adaptor\SentryAdaptor;
use PhpTek\Sentry\Adaptor\SentrySeverity;
use PhpTek\Sentry\Helper\SentryHelper;
use PhpTek\Sentry\Log\SentryLogger;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\SentrySdk;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\State\Scope;
use Sentry\Tracing\DynamicSamplingContext;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\TransactionMetadata;
use Sentry\Tracing\TransactionSource;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment as Env;
use SilverStripe\Core\Injector\Injector;

class SentryProfilingMiddleware implements HTTPMiddleware
{
    public function startTransaction($request)
    {
        $client = ClientBuilder::create(
            SentryAdaptor::get_opts() ?: []
        )->getClient();
        SentrySdk::setCurrentHub(new Hub($client));

        $config["traces_sample_rate"] = SentryAdaptor::get_opts()["traces_sample_rate"] ?? null;
        $config["profiles_sample_rate"] = SentryAdaptor::get_opts()["profiles_sample_rate"] ?? null;

        $logger = SentryLogger::factory($client, $config);
        $transaction = $logger->adaptor->startTransaction(
            "/" . $request->getURL()
        );
        SentrySdk::getCurrentHub()->setSpan($transaction);

        return $transaction;
    }

    public function endTransaction($transaction)
    {
        $transaction->finish();
    }

    public function process(HTTPRequest $request, callable $delegate)
    {
        if (!Director::is_cli()) {
            $tx = $this->startTransaction($request);
        }
        $response = $delegate($request);
        if (!Director::is_cli()) {
            $tx->setHttpStatus($response->getStatusCode());
            $this->endTransaction($tx);
        }

        return $response;
    }
}
