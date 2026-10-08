<?php

namespace App\Enums;

/**
 * Every `error.code` the API answers with (API contract §6).
 *
 * The apps show the text for a code from their own resource files; the
 * `message` beside it is for developers only. Values the text needs (minutes,
 * dates, limits) travel in `error.details`.
 */
enum ErrorCode: string
{
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case ValidationFailed = 'VALIDATION_FAILED';
    case RateLimited = 'RATE_LIMITED';
    case AppVersionUnsupported = 'APP_VERSION_UNSUPPORTED';
    case ServerError = 'SERVER_ERROR';

    // Customer sign-in and account
    case OtpInvalid = 'OTP_INVALID';
    case OtpExpired = 'OTP_EXPIRED';
    case OtpAttemptsExceeded = 'OTP_ATTEMPTS_EXCEEDED';
    case OtpResendTooSoon = 'OTP_RESEND_TOO_SOON';
    case PolicyVersionOutdated = 'POLICY_VERSION_OUTDATED';
    case PolicyConsentRequired = 'POLICY_CONSENT_REQUIRED';
    case ProfileIncomplete = 'PROFILE_INCOMPLETE';
    case ProfileAlreadyCompleted = 'PROFILE_ALREADY_COMPLETED';
    case UnderAge = 'UNDER_AGE';

    // Merchant registration and PIN
    case RegistrationIncomplete = 'REGISTRATION_INCOMPLETE';
    case RegistrationStepMismatch = 'REGISTRATION_STEP_MISMATCH';
    case PinRequired = 'PIN_REQUIRED';
    case PinInvalid = 'PIN_INVALID';
    case PinLocked = 'PIN_LOCKED';
    case PinResetRequiresRecentLogin = 'PIN_RESET_REQUIRES_RECENT_LOGIN';

    // Scanning, stamps and rewards
    case QrInvalid = 'QR_INVALID';
    case QrExpired = 'QR_EXPIRED';
    case ScanTokenExpired = 'SCAN_TOKEN_EXPIRED';
    case StampInterval = 'STAMP_INTERVAL';
    case RewardReadyRedeemFirst = 'REWARD_READY_REDEEM_FIRST';
    case NoRewardReady = 'NO_REWARD_READY';
    case RedeemRequiresQr = 'REDEEM_REQUIRES_QR';
    case RewardAlreadyRedeemed = 'REWARD_ALREADY_REDEEMED';
    case CardSuspended = 'CARD_SUSPENDED';
    case MerchantStatusBlocksAction = 'MERCHANT_STATUS_BLOCKS_ACTION';
    case CardsLimitReached = 'CARDS_LIMIT_REACHED';

    // Campaigns, birthdays and payments
    case CampaignWeeklyLimitReached = 'CAMPAIGN_WEEKLY_LIMIT_REACHED';
    case CampaignContainsLink = 'CAMPAIGN_CONTAINS_LINK';
    case BirthdayNotToday = 'BIRTHDAY_NOT_TODAY';
    case PaymentAlreadyPending = 'PAYMENT_ALREADY_PENDING';
    case KeepCardsRequired = 'KEEP_CARDS_REQUIRED';
    case PriceNotAvailable = 'PRICE_NOT_AVAILABLE';

    // Dashboard (outside the contract).
    case PaymentNotPending = 'PAYMENT_NOT_PENDING';
    case TrialNotExtendable = 'TRIAL_NOT_EXTENDABLE';

    public function status(): int
    {
        return match ($this) {
            self::Unauthenticated => 401,
            self::Forbidden,
            self::PolicyConsentRequired,
            self::ProfileIncomplete,
            self::RegistrationIncomplete,
            self::PinRequired,
            self::PinResetRequiresRecentLogin => 403,
            self::NotFound => 404,
            self::ProfileAlreadyCompleted,
            self::RegistrationStepMismatch,
            self::RewardAlreadyRedeemed,
            self::PaymentAlreadyPending,
            self::PaymentNotPending => 409,
            self::AppVersionUnsupported => 426,
            self::RateLimited,
            self::OtpResendTooSoon,
            self::PinLocked => 429,
            self::ServerError => 500,
            default => 422,
        };
    }
}
