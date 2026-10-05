<?php

namespace Domain\Wallet\ValueObjects;

enum TransactionType: string
{
    case CreditPurchase = 'credit_purchase';
    case CoursePayment = 'course_payment';
    case AdminAdjustment = 'admin_adjustment';
    case Refund = 'refund';
}
