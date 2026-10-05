<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Infrastructure\Persistence\Eloquent\Models\TicketMessage as BaseTicketMessage;

#[Fillable(['ticket_id', 'author_id', 'body', 'is_internal'])]
class TicketMessage extends BaseTicketMessage {}
