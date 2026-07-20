<?php

namespace App\Services\AdminState\Loaders;

/**
 * @deprecated Use PaymentsLoader. Kept for DI/back-compat; payments hub owns receipts.
 */
class ReceiptsLoader extends PaymentsLoader
{
}
