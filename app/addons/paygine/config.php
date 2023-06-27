<?php

defined('BOOTSTRAP') or die('Access denied');

const PAYGINE_PROCESSOR = 'paygine.php';
const PAYGINE_OPERATION_APPROVED = 'APPROVED';
const PAYGINE_SUPPORTED_TYPES = [
	'PURCHASE',
	'PURCHASE_BY_QR',
	'AUTHORIZE',
	'REVERSE',
	'COMPLETE'
];
const PAYGINE_PAYMENT_TYPES = [
	'PURCHASE',
	'PURCHASE_BY_QR',
	'AUTHORIZE'
];