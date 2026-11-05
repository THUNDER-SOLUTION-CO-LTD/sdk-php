# Thunder SDK สำหรับ PHP

[![CI](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/actions/workflows/ci.yml/badge.svg)](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

[Thunder](https://document.thunder.in.th) ตรวจสลิปโอนเงินของธนาคารไทยและสลิป TrueMoney Wallet กับธนาคารให้ จึงรู้ได้ว่าลูกค้าโอนเงินมาจริงก่อนยืนยันออเดอร์ SDK นี้ช่วยให้แอป PHP ของคุณตรวจสลิปได้ในไม่กี่บรรทัด เช็กได้ว่าเงินเข้าบัญชีของคุณ ยอดเงินถูกต้อง และไม่ใช่สลิปซ้ำ รวมถึงรับผลการตรวจเบื้องหลังผ่านเว็บฮุคที่มีลายเซ็นยืนยัน

[Read in English](README.md)

## ความสามารถ

- ตรวจสลิปโอนเงินได้จากรูปสลิป ข้อความในคิวอาร์โค้ด URL ของรูป หรือ base64
- เช็กในคำขอเดียวว่าสลิปโอนเข้าบัญชีรับเงินของคุณ ยอดเงินตรงกับออเดอร์ และไม่เคยถูกใช้มาก่อน
- ตรวจสลิป TrueMoney Wallet ได้ด้วยเงื่อนไขชุดเดียวกัน
- ส่งสลิปไปตรวจเบื้องหลังได้ครั้งละสูงสุด 100 ใบ แล้วรับผลแต่ละใบทางเว็บฮุค
- จัดการบัญชีธนาคารที่ใช้รับเงิน
- สร้างคิวอาร์ PromptPay และคิวอาร์ชำระบิลให้ลูกค้าสแกนจ่าย
- แยกคลาส exception ตามสาเหตุ และระบบลองใหม่อัตโนมัติจะไม่ทำให้สลิปใบเดียวถูกคิดโควต้าซ้ำ
- ผลการตรวจเป็น object ที่มี type ครบ IDE และ PHPStan อ่านเข้าใจ ใช้กับ HTTP client แบบ PSR-18 ตัวไหนก็ได้ ค่าเริ่มต้นคือ Guzzle

## สิ่งที่ต้องมี

- PHP 8.2, 8.3, 8.4 หรือ 8.5 ที่เปิด extension `fileinfo` ไว้ ซึ่ง PHP ส่วนใหญ่มีมาให้อยู่แล้ว
- [Composer](https://getcomposer.org)
- API key ของ Thunder เข้าสู่ระบบที่ [Thunder developer portal](https://developer.thunder.in.th) แล้วคัดลอก API key ของแอปพลิเคชันมาใช้

## การติดตั้ง

```sh
composer require thunder-solution/sdk
```

คำสั่งนี้ติดตั้ง Guzzle ที่ SDK ใช้ส่งคำขอมาให้ด้วย ถ้า Composer ถามว่าจะเชื่อถือ plugin `php-http/discovery` หรือไม่ ตอบแบบไหนก็ได้ เพราะ SDK หา Guzzle เจอได้โดยไม่ต้องใช้ plugin นี้

## เริ่มต้นใช้งาน

โปรแกรมนี้ตรวจสลิปที่ลูกค้าส่งมาจ่ายออเดอร์ยอด 1,500 บาท โดยเช็กว่าเงินเข้าบัญชีรับเงินที่ลงทะเบียนไว้ ยอดเงินถูกต้อง และสลิปไม่ซ้ำ จากนั้นพิมพ์รายละเอียดการโอนออกมา

บันทึกโค้ดเป็นไฟล์ `verify.php` ในโฟลเดอร์โปรเจกต์ แล้ววางรูปสลิปไว้ข้างกันในชื่อ `slip.jpg`

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\SlipNotFoundError;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder(); // อ่าน API key จาก THUNDER_API_KEY
$orderTotal = 1500; // บาท

try {
    $result = $thunder->verify->bankByImage(FileInput::fromPath(__DIR__ . '/slip.jpg'), new VerifyOptions(
        matchAccount: true, // เงินเข้าบัญชีรับเงินของเราไหม
        matchAmount: $orderTotal, // ยอดเงินถูกไหม
        checkDuplicate: true, // เป็นสลิปซ้ำไหม
    ));
    $slip = $result->rawSlip;

    if ($result->isDuplicate) {
        echo "สลิปนี้เคยใช้แล้ว อย่ายืนยันออเดอร์ซ้ำ\n";
    } elseif (!$result->isAmountMatched) {
        echo "สลิปนี้โอน {$result->amountInSlip} บาท แต่ออเดอร์ยอด {$orderTotal} บาท\n";
    } elseif ($result->matchedAccount === null) {
        echo "สลิปนี้ไม่ได้โอนเข้าบัญชีรับเงินของเรา\n";
    } else {
        echo "ยืนยันการชำระเงินแล้ว\n";
        echo "ยอดเงิน:   {$slip->amount->amount} บาท\n";
        echo "วันที่:     {$slip->date}\n";
        echo "ผู้โอน:    {$slip->sender->account?->name->th} ({$slip->sender->bank?->short})\n";
        echo "ผู้รับ:     {$slip->receiver->account?->name->th} ({$slip->receiver->bank?->short})\n";
        echo "เลขอ้างอิง: {$slip->transRef}\n";
    }
} catch (SlipNotFoundError) {
    echo "ไม่พบรายการโอนนี้ที่ธนาคาร สลิปอาจเป็นของปลอมหรือถูกแก้ไข\n";
}
```

รันพร้อม API key ของคุณ

```sh
THUNDER_API_KEY="your-api-key" php verify.php
```

ถ้าสลิปถูกต้อง จะได้ผลแบบนี้

```text
ยืนยันการชำระเงินแล้ว
ยอดเงิน:   1500 บาท
วันที่:     2024-01-15T14:30:00+07:00
ผู้โอน:    นาย ผู้โอน ทดสอบ (KBANK)
ผู้รับ:     บริษัท ตัวอย่าง จำกัด (SCB)
เลขอ้างอิง: 68370160657749I376388B35
```

`matchAccount` เทียบบัญชีผู้รับกับบัญชีรับเงินที่คุณลงทะเบียนไว้ จึงต้องเพิ่มบัญชีรับเงินก่อน ทำได้ใน developer portal หรือใช้ [`bankAccounts->create`](#receiving-bank-accounts) การตรวจสลิปแต่ละครั้งใช้โควต้า 1 ครั้ง

อ่านต่อ

- [ตรวจสลิปจากข้อมูลรูปแบบอื่น](#verify-a-bank-slip) เช่น ข้อความในคิวอาร์หรือ URL ของรูป
- [ดูฟิลด์ทั้งหมดของผลการตรวจ](#the-result-object)
- [จัดการ error](#error-handling) และดูว่า SDK ลองใหม่ให้ในกรณีไหนบ้าง
- [รับผลการตรวจเบื้องหลังทางเว็บฮุค](#webhooks)

## วิธีใช้งาน

ตัวอย่างในส่วนนี้สร้าง client ใหม่ทุกครั้งเพื่อให้คัดลอกไปใช้ได้ทันที ในแอปจริงให้สร้าง client ครั้งเดียวแล้วใช้ซ้ำ

<a id="verify-a-bank-slip"></a>

### ตรวจสลิปธนาคาร

เลือกเมธอดตามข้อมูลที่ลูกค้าส่งมา ทั้งสี่เมธอดคืน[ผลการตรวจ](#the-result-object)แบบเดียวกันและรับ[ตัวเลือก](#check-amount-receiving-account-and-duplicates)ชุดเดียวกัน

| ข้อมูลที่มี | เมธอด |
| --- | --- |
| ไฟล์รูป ไฟล์ที่อัปโหลดมา หรือ stream | `verify->bankByImage($file)` |
| ข้อความในคิวอาร์โค้ดของสลิปที่อ่านด้วยตัวสแกน | `verify->bankByPayload($payload)` |
| URL แบบ https ของรูปสลิปที่เปิดได้จากภายนอก | `verify->bankByUrl($url)` |
| รูปที่เข้ารหัสเป็น base64 จะมี `data:` นำหน้าหรือไม่ก็ได้ | `verify->bankByBase64($base64)` |

`bankByImage` รับ `FileInput` สร้างได้จาก path ของไฟล์ด้วย `FileInput::fromPath()` จาก string ของ bytes ด้วย `FileInput::fromBytes()` จาก stream ที่เปิดไว้ด้วย `FileInput::fromStream()` หรือจาก stream แบบ PSR-7 ด้วย `FileInput::fromPsrStream()` ถ้าเป็นไฟล์ที่อัปโหลดผ่านฟอร์มใน PHP ธรรมดา ให้ส่ง `$_FILES['slip']['tmp_name']` เข้า `fromPath()` รูปต้องเป็น JPEG, PNG, GIF หรือ WebP ขนาดไม่เกิน 4 MB โดย SDK ดูจากเนื้อไฟล์จริง ไม่ได้ดูจากชื่อไฟล์

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;

$thunder = new Thunder();

$result = $thunder->verify->bankByUrl('https://example.com/slips/1001.jpg');
echo $result->rawSlip->transRef, ' ', $result->amountInSlip, "\n";
```

<a id="check-amount-receiving-account-and-duplicates"></a>

### เช็กยอดเงิน บัญชีรับเงิน และสลิปซ้ำ

เปิดการตรวจที่ต้องการด้วย named argument ของ `VerifyOptions` ตัวเลือกแต่ละตัวจะเพิ่มฟิลด์ในผลการตรวจ ถ้าไม่ใส่ก็จะไม่มีฟิลด์นั้น เมื่อเงื่อนไขไม่ผ่าน API ยังคืนข้อมูลสลิปมาให้ตามปกติ โค้ดของคุณเป็นคนตัดสินว่าจะรับยอดหรือไม่

| ตัวเลือก | ตรวจอะไร | ฟิลด์ในผลการตรวจ | เมื่อไม่ผ่าน |
| --- | --- | --- | --- |
| `matchAmount: 1500` | ยอดเงินในสลิปตรงกับยอดที่คาดไว้ หน่วยเป็นบาท | `amountInOrder`, `isAmountMatched` | `isAmountMatched` เป็น `false` |
| `matchAccount: true` | ผู้รับเงินเป็นบัญชีรับเงินที่ลงทะเบียนไว้ | `matchedAccount` | `matchedAccount` เป็น `null` |
| `checkDuplicate: true` | สลิปนี้เคยถูกตรวจมาแล้ว | `isDuplicate` | `isDuplicate` เป็น `true` |
| `remark: 'Order 1001'` | ไม่ได้ตรวจอะไร แค่บันทึกโน้ตยาว 1 ถึง 255 ตัวอักษรไว้กับการตรวจครั้งนี้ | `remark` | ไม่มีกรณีไม่ผ่าน |

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder();

$result = $thunder->verify->bankByImage(FileInput::fromPath('slip.jpg'), new VerifyOptions(
    remark: 'Order 1001',
    matchAccount: true,
    matchAmount: 1500,
    checkDuplicate: true,
));
$accepted = $result->isAmountMatched === true && $result->matchedAccount !== null && $result->isDuplicate === false;
echo $accepted ? 'รับยอด' : 'ไม่รับยอด', ' ', $result->remark, "\n";
```

ถ้าสาขาเดียวกันเคยตรวจสลิปนี้แล้ว API จะคืนผลเดิมที่บันทึกไว้และไม่ใช้โควต้า ถ้าเป็นสาขาอื่นที่เคยตรวจ API จะตรวจกับธนาคารใหม่และใช้โควต้า ทั้งสองกรณี `isDuplicate` เป็น `true`

### สลิป TrueMoney Wallet

สลิป TrueMoney Wallet ใช้งานแบบเดียวกันและรับตัวเลือกชุดเดียวกัน แต่ข้อมูลสลิปใน[ผลการตรวจ](#truemoney-wallet-receipt)สั้นกว่า

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

$thunder = new Thunder();

$result = $thunder->verify->truewalletByImage(FileInput::fromPath('wallet-receipt.png'), new VerifyOptions(
    matchAmount: 500,
    checkDuplicate: true,
));
echo $result->rawSlip->transactionId, ' ', $result->rawSlip->amount, ' ', $result->rawSlip->receiver->name, "\n";
```

ใช้ `verify->truewalletByUrl()` เมื่อมี URL ของรูป และ `verify->truewalletByBase64()` เมื่อมี base64

### ข้อมูลบัญชีและโควต้าคงเหลือ

`getInfo()` คืนข้อมูลแอปพลิเคชัน สาขา และโควต้า ถ้าแพ็กเกจไม่จำกัดโควต้า `quota->remaining` จะเป็น `null`

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;

$thunder = new Thunder();

$info = $thunder->getInfo();
$quota = $info->application->quota;
echo "{$info->application->name}, สาขา {$info->branch->name}\n";
echo 'ใช้ไป ', $quota->used, ' คงเหลือ ', $quota->remaining ?? 'ไม่จำกัด', "\n";
```

<a id="receiving-bank-accounts"></a>

### บัญชีรับเงิน

`matchAccount` เทียบผู้รับเงินในสลิปกับบัญชีกลุ่มนี้ บัญชีรับเงินเป็นของบริการ (service) และแต่ละสาขาจะเทียบเฉพาะบัญชีที่ผูกกับสาขานั้น `bankAccounts->create()` เพิ่มบัญชีแล้วผูกกับสาขาของ API key ให้ทันที

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Enum\AccountType;
use Thunder\Input\CreateBankAccountParams;
use Thunder\Input\ListBankAccountsParams;
use Thunder\Input\UpdateBankAccountParams;

$thunder = new Thunder();

$account = $thunder->bankAccounts->create(new CreateBankAccountParams(
    bankCode: '004', // รหัสธนาคารดูได้จาก $thunder->banks->list()
    bankNumber: '1234567890',
    nameTh: 'บริษัท ตัวอย่าง จำกัด',
    nameEn: 'EXAMPLE CO., LTD.',
    type: AccountType::JURISTIC,
));
$thunder->bankAccounts->update($account->id, new UpdateBankAccountParams(nameEn: 'EXAMPLE COMPANY LIMITED'));

// บัญชีที่ผูกกับสาขานี้ทีละหน้า
$page = $thunder->bankAccounts->list(new ListBankAccountsParams(page: 1, limit: 20));
echo "ผูกกับสาขานี้ {$page->total} บัญชี\n";

// ทุกบัญชีในบริการ SDK ดึงทุกหน้าให้เอง
foreach ($thunder->bankAccounts->iterateAll() as $item) {
    echo $item->id, ' ', $item->bankNumber, ' ', $item->nameEn, "\n";
}
```

| เมธอด | ใช้ทำอะไร |
| --- | --- |
| `bankAccounts->create($params)` | เพิ่มบัญชีเข้าบริการและผูกกับสาขานี้ |
| `bankAccounts->get($id)` | ดึงข้อมูลบัญชีเดียว |
| `bankAccounts->update($id, $params)` | แก้เฉพาะฟิลด์ที่ส่งมา ส่ง `extraVerify: null` เพื่อล้างค่านั้น |
| `bankAccounts->link($id)` | ผูกบัญชีของบริการกับสาขานี้ ผูกซ้ำก็ไม่เป็นไร |
| `bankAccounts->unlink($id)` | ยกเลิกการผูกกับสาขานี้ บัญชียังอยู่ในบริการ |
| `bankAccounts->list($params)` | ดึงบัญชีที่ผูกกับสาขานี้หนึ่งหน้า `page` เริ่มที่ 1 และ `limit` อยู่ระหว่าง 1 ถึง 100 (ค่าเริ่มต้น 20) |
| `bankAccounts->listAll($params)` | ดึงบัญชีทั้งหมดของบริการหนึ่งหน้า แบ่งหน้าแบบเดียวกัน |
| `bankAccounts->iterate($params)` | ไล่ดูทุกบัญชีที่ผูกกับสาขานี้ ดึงหน้าละ 100 บัญชี |
| `bankAccounts->iterateAll($params)` | ไล่ดูทุกบัญชีของบริการ ดึงหน้าละ 100 บัญชี |

เมธอดดึงรายการทั้งสี่ตัวรับ `ListBankAccountsParams` ที่กรองด้วย `type` (`AccountType::NATURAL` หรือ `AccountType::JURISTIC`) และ `bankCode` ได้ ถ้าเพิ่มบัญชีที่มีอยู่แล้วจะได้ `ConflictError`

<a id="verify-many-slips-in-the-background"></a>

### ตรวจสลิปจำนวนมากเบื้องหลัง

งานเบื้องหลัง (job) จะตรวจสลิปที่ฝั่ง Thunder แล้วส่งผลไปที่[เว็บฮุค](#webhooks)ของคุณ เหมาะเมื่อมีสลิปจำนวนมากหรือไม่อยากรอธนาคารตอบ ต้องส่ง `callbackUrl` แบบ https ทุกครั้ง เว้นแต่สาขาตั้ง Default Webhook URL ไว้แล้ว

ตัวอย่างนี้อ่านข้อความคิวอาร์ของสลิปหลายใบจาก `payloads.txt` บรรทัดละใบ แล้วส่งตรวจเป็นชุดเดียว

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Input\BatchOptions;
use Thunder\Input\BatchSlip;

$thunder = new Thunder();
$payloads = file('payloads.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

$batch = $thunder->jobs->submitBankBatch(
    array_map(static fn(string $payload): BatchSlip => new BatchSlip($payload, checkDuplicate: true), $payloads),
    new BatchOptions(callbackUrl: 'https://example.com/webhooks/thunder'),
);

// เว็บฮุคจะได้รับผลทุกใบ หรือจะรอผลของแต่ละงานตรงนี้ก็ได้
foreach ($batch->jobs as $submitted) {
    $job = $thunder->jobs->waitForJob($submitted->jobId);
    echo $job->jobId, ' ', $job->status, ' ', $job->result?->status, ' ', $job->result?->data?->amountInSlip, "\n";
}
```

| เมธอด | ใช้ทำอะไร |
| --- | --- |
| `jobs->submitBank($payload, $options)` | ส่งสลิปหนึ่งใบ `AsyncVerifyOptions` รับตัวเลือกเหมือน `VerifyOptions` และเพิ่ม `callbackUrl` |
| `jobs->submitBankBatch($slips, $options)` | ส่งสลิป 1 ถึง 100 ใบในคำขอเดียว `BatchSlip` แต่ละใบตั้งตัวเลือกของตัวเองได้ และ `BatchOptions` ใช้ใส่ `callbackUrl` |
| `jobs->submitBankMany($slips, $options)` | ส่งสลิปกี่ใบก็ได้ โดยแบ่งเป็นชุดละ 100 ใบแล้วส่งทีละชุด |
| `jobs->get($jobId)` | ดึงสถานะงาน และผลการตรวจเมื่องานเสร็จ |
| `jobs->waitForJob($jobId, $options)` | เช็กงานทุก `interval` วินาที (ค่าเริ่มต้น 2) จนงานเป็น `done` หรือ `failed` รอนานสุด `timeout` วินาที (ค่าเริ่มต้น 600 หรือ 10 นาที) ส่ง `WaitOptions` เพื่อเปลี่ยนค่าเหล่านี้ |

### สร้างคิวอาร์รับเงิน

API สร้างคิวอาร์ PromptPay และคิวอาร์ชำระบิลให้ลูกค้าสแกนจ่ายได้ การสร้างคิวอาร์ไม่ใช้โควต้า `amount` มีหน่วยเป็นบาท ใส่ได้ตั้งแต่ 0.01 ถึง 9,999,999,999.99 ทศนิยมไม่เกิน 2 ตำแหน่ง ถ้าไม่ใส่ ผู้จ่ายจะกรอกยอดเอง

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Enum\QrType;
use Thunder\Input\QrRequest;

$thunder = new Thunder();

// PromptPay เข้าเบอร์โทร ยอด 250 บาท
$promptPay = $thunder->v1->generateQr(new QrRequest(QrType::PROMPTPAY, msisdn: '0812345678', amount: 250));
file_put_contents('promptpay.png', $promptPay->imageBytes());

// ชำระบิลเข้า Biller ID ของคุณ ใช้เลขใบแจ้งหนี้เป็น Ref1
$bill = $thunder->v1->generateQr(new QrRequest(
    QrType::PROMPTPAY,
    billerId: '010555512345601',
    ref1: 'INV1001',
    amount: 1500,
));
file_put_contents('bill.png', $bill->imageBytes());
```

| `type` | ฟิลด์ที่ต้องใส่ | ฟิลด์ที่ใส่หรือไม่ก็ได้ |
| --- | --- | --- |
| `QrType::PROMPTPAY` | อย่างใดอย่างหนึ่ง: `msisdn` (เบอร์โทร), `natId` (เลขบัตรประชาชนหรือเลขผู้เสียภาษี), `eWalletId` หรือ `billerId` | `amount` และใส่ `ref1`, `ref2` ได้เมื่อใช้ `billerId` |
| `QrType::KSHOP` | `ref1` คือรหัสร้าน ขึ้นต้นด้วย `KB` ตามด้วยตัวเลข 12 หลัก | `amount` |
| `QrType::MAE_MANEE` | `ref1` คือรหัสร้าน 15 หลัก | `amount` |
| `QrType::TUNGNGERN` | `ref1` คือรหัสร้านค้า และ `merchantName` คือชื่อร้านภาษาอังกฤษ | `amount` |

type `QrType::BILL_PAYMENT` แบบเดิมยังใช้ได้ ถ้าใช้ `billerId` เดียวกันจะได้คิวอาร์เดียวกับ `QrType::PROMPTPAY`

### API v1 แบบเดิม

ใช้ `$thunder->v1` เฉพาะระบบที่เชื่อมกับ API v1 อยู่แล้ว โค้ดใหม่ควรใช้เมธอด v2 ด้านบน เมธอดของ v1 คืนข้อมูลสลิปโดยตรง และเมื่อใส่ `checkDuplicate: true` แล้วเจอสลิปซ้ำจะโยน `DuplicateSlipError`

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\DuplicateSlipError;
use Thunder\Input\FileInput;
use Thunder\Input\V1VerifyOptions;

$thunder = new Thunder();

try {
    $slip = $thunder->v1->verifyBankByImage(FileInput::fromPath('slip.jpg'), new V1VerifyOptions(checkDuplicate: true));
    echo $slip->transRef, ' ', $slip->amount->amount, "\n";
} catch (DuplicateSlipError $error) {
    echo 'สลิปนี้เคยใช้แล้ว วันที่โอน: ', $error->slip?->date, "\n";
}
```

API v1 ยังมี `verifyBankByPayload()`, `verifyBankByUrl()`, `verifyBankByBase64()`, `verifyTruemoneyByImage()` และ `getMe()`

### ตรวจหลายใบพร้อมกัน

ทุกเมธอดรอจน API ตอบกลับแล้วจึงคืนค่า SDK นี้ไม่มี API แบบ asynchronous ถ้ามีสลิปจำนวนมากและไม่อยากให้คำขอของหน้าเว็บค้างรอ ให้ส่งเป็น[งานเบื้องหลัง](#verify-many-slips-in-the-background)แล้วรับผลทางเว็บฮุค หรือตรวจใน queue worker ของคุณ อย่ายิงคำขอพร้อมกันมากเกินไป เพราะ API จำกัดจำนวนคำขอต่อช่วงเวลา

<a id="the-result-object"></a>

## ผลการตรวจ

การตรวจแบบ v2 คืนผลของเงื่อนไขที่คุณขอไว้ที่ชั้นบนสุด และข้อมูลสลิปตามที่ธนาคารส่งมาไว้ใน `rawSlip` ดูโครงสร้างเต็มได้ที่[เอกสาร API](https://document.thunder.in.th/th/v2/verify/bank/)

### สลิปธนาคาร

`verify->bankBy...()` คืน `BankSlipResult`

| property | ชนิด | ความหมาย | ตัวอย่าง |
| --- | --- | --- | --- |
| `amountInSlip` | `float` | ยอดเงินที่โอน หน่วยเป็นบาท | `1500.0` |
| `isAmountMatched` | `?bool` | ยอดเงินตรงกับ `matchAmount` หรือไม่ มีเฉพาะเมื่อใส่ `matchAmount` ถ้าไม่ใส่จะเป็น `null` | `true` |
| `amountInOrder` | `?float` | ค่า `matchAmount` ที่ส่งไป มีเฉพาะเมื่อใส่ `matchAmount` | `1500.0` |
| `matchedAccount` | `?MatchedAccount` | บัญชีรับเงินของคุณที่สลิปโอนเข้า หรือ `null` ถ้าไม่ตรงบัญชีไหน มีเฉพาะเมื่อใส่ `matchAccount` | `bankNumber` เป็น `'123-4-56789-0'` |
| `isDuplicate` | `?bool` | สลิปนี้เคยถูกตรวจแล้วหรือไม่ มีเฉพาะเมื่อใส่ `checkDuplicate` | `false` |
| `remark` | `?string` | ค่า `remark` ที่ส่งไป มีเฉพาะเมื่อส่ง | `'Order 1001'` |
| `rawSlip->transRef` | `string` | เลขอ้างอิงรายการของธนาคาร แต่ละรายการโอนไม่ซ้ำกัน เก็บไว้เพื่อกันสลิปซ้ำได้ | `'68370160657749I376388B35'` |
| `rawSlip->date` | `string` | เวลาที่โอน รูปแบบ ISO 8601 พร้อมเขตเวลาไทย | `'2024-01-15T14:30:00+07:00'` |
| `rawSlip->amount->amount` | `float` | ยอดเงิน หน่วยเป็นบาท | `1500.0` |
| `rawSlip->fee` | `?float` | ค่าธรรมเนียม เมื่อธนาคารแจ้งมา | `0.0` |
| `rawSlip->sender->bank` | `?SlipBank` | ธนาคารผู้โอน: `id` (รหัสธนาคาร), `name` และ `short` | `'004'`, `'กสิกรไทย'`, `'KBANK'` |
| `rawSlip->sender->account->name` | `AccountName` | ชื่อผู้โอนภาษาไทย (`th`) และอังกฤษ (`en`) เมื่อธนาคารแสดง | `'นาย ผู้โอน ทดสอบ'`, `'MR. SENDER TEST'` |
| `rawSlip->sender->account->bank` | `?AccountNumber` | เลขบัญชีผู้โอน (`account`) และชนิด (`type`) ธนาคารปิดบางหลักไว้ | `'BANKAC'`, `'123-4-xxxxx-5'` |
| `rawSlip->receiver->bank` | `?SlipBank` | ธนาคารผู้รับ รูปแบบเดียวกับผู้โอน | `'014'`, `'ไทยพาณิชย์'`, `'SCB'` |
| `rawSlip->receiver->account->name` | `AccountName` | ชื่อผู้รับ | `'บริษัท ตัวอย่าง จำกัด'` |
| `rawSlip->receiver->account->bank` | `?AccountNumber` | เลขบัญชีผู้รับ ปิดบางหลักไว้ | `'BANKAC'`, `'xxx-x-x5678-x'` |
| `rawSlip->receiver->account->proxy` | `?AccountNumber` | PromptPay ที่ใช้รับเงิน เมื่อโอนผ่าน PromptPay | `'MSISDN'`, `'08xxxxxxxx89'` |
| `rawSlip->ref1`, `ref2`, `ref3` | `?string` | เลขอ้างอิงของการชำระบิล เป็นค่าว่างสำหรับการโอนทั่วไป | `''` |

`rawSlip->sender->account` และ `rawSlip->receiver->account` เป็น `null` ได้เมื่อธนาคารไม่แจ้งข้อมูลบัญชี จึงควรอ่านด้วย `?->` แบบในตัวอย่างเริ่มต้นใช้งาน

<a id="truemoney-wallet-receipt"></a>

### สลิป TrueMoney Wallet

`verify->truewalletBy...()` คืน `TrueWalletResult` property ชั้นบนสุดเหมือนสลิปธนาคาร ส่วน `rawSlip` มีข้อมูลดังนี้

| property | ชนิด | ความหมาย | ตัวอย่าง |
| --- | --- | --- | --- |
| `rawSlip->transactionId` | `string` | เลขรายการของ TrueMoney | `'50012345678901'` |
| `rawSlip->date` | `string` | เวลาที่ชำระ รูปแบบ ISO 8601 | `'2026-10-26T14:30:00+07:00'` |
| `rawSlip->amount` | `float` | ยอดเงิน หน่วยเป็นบาท | `500.0` |
| `rawSlip->sender->name` | `string` | ชื่อผู้โอน | `'นาย ผู้โอน ทดสอบ'` |
| `rawSlip->receiver->name` | `string` | ชื่อผู้รับ | `'นาย รับเงิน ทดสอบ'` |
| `rawSlip->receiver->phone` | `string` | เบอร์โทรผู้รับ ปิดบางหลักไว้ | `'08x-xxx-4567'` |

ผลการตรวจทุกตัวมี property `meta` ที่เก็บ HTTP `status`, `requestId`, ตัวนับ `rateLimit` และ `headers` ของ response ค่านี้ไม่ได้เป็นส่วนของข้อมูล `json_encode($result)` จึงคืนข้อมูลตามที่ API ส่งมาโดยไม่รวม `meta`

## การตั้งค่า

ส่ง named argument เข้า `new Thunder(...)` ตัวเลือกที่ไม่ได้ใส่จะอ่านจากตัวแปร environment ถ้าไม่มีก็ใช้ค่าเริ่มต้น

| ตัวเลือก | ตัวแปร environment | ค่าเริ่มต้น | ใช้ทำอะไร |
| --- | --- | --- | --- |
| `apiKey` | `THUNDER_API_KEY` | ไม่มี | API key ของคุณ ถ้าไม่มี key เลย `new Thunder()` จะโยน `InputError` |
| ไม่มีตัวเลือก | `THUNDER_API_KEY_FILE` | ไม่มี | path ของไฟล์ที่เก็บ API key เช่น Docker secret หรือ Kubernetes secret ใช้เมื่อไม่มีทั้ง `apiKey` และ `THUNDER_API_KEY` |
| `timeout` | ไม่มีตัวแปร | `30.0` | เวลาสูงสุดของการส่งคำขอแต่ละครั้ง หน่วยวินาที |
| `maxRetries` | ไม่มีตัวแปร | `2` | จำนวนครั้งที่ลองใหม่เมื่อคำขอล้มเหลว ใส่ได้ 0 ถึง 10 ใส่ `0` เพื่อปิด |
| `httpClient` | ไม่มีตัวแปร | client ของ Guzzle | HTTP client แบบ PSR-18 ที่ SDK ใช้ส่งคำขอ |
| `requestFactory` | ไม่มีตัวแปร | หาให้ด้วย `php-http/discovery` | factory แบบ PSR-17 ที่ SDK ใช้สร้างคำขอ |
| `v2BaseUrl` | `THUNDER_V2_BASE_URL` | `https://api.thunder.in.th/v2` | ที่อยู่ของ API v2 |
| `v1BaseUrl` | `THUNDER_V1_BASE_URL` | `https://api.thunder.in.th/v1` | ที่อยู่ของ API v1 |
| `sleep` | ไม่มีตัวแปร | `usleep()` | ฟังก์ชันที่ใช้รอระหว่างการลองใหม่และระหว่างการเช็กงาน รับเวลารอเป็นวินาที ในเทสต์ส่งฟังก์ชันที่คืนค่าทันทีได้ |

`Thunder` กับ `Client` เป็นคลาสเดียวกัน จะใช้ `new Client(...)` ก็ได้

<a id="custom-http-client"></a>

### ใช้ HTTP client ของคุณเอง

ส่ง client แบบ PSR-18 ของคุณเข้าไปได้ เช่น เพื่อเก็บ log ส่งผ่าน proxy หรือบันทึกคำขอในเทสต์ ตัวอย่างนี้ครอบ Guzzle ไว้และเก็บ log ทุกคำขอ

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use GuzzleHttp\Client as Guzzle;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class LoggingClient implements ClientInterface
{
    public function __construct(private ClientInterface $inner) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $started = microtime(true);
        $response = $this->inner->sendRequest($request);
        error_log(sprintf('%s %s %d %.0f ms', $request->getMethod(), $request->getUri(), $response->getStatusCode(), (microtime(true) - $started) * 1000));
        return $response;
    }
}

$thunder = new Thunder(httpClient: new LoggingClient(new Guzzle(['timeout' => 30, 'connect_timeout' => 10])));
echo $thunder->getInfo()->application->name, "\n";
```

ถ้า `httpClient` เป็น client ของ Guzzle SDK จะใช้ `timeout` กับการส่งแต่ละครั้งและปิดการตาม redirect ให้ ถ้าเป็น `Psr18Client` ของ Symfony SDK จะใช้ `timeout` ให้ ส่วน client อื่น รวมถึงตัวครอบในตัวอย่างนี้ ต้องตั้ง timeout เองและต้องไม่ตาม redirect

ไม่ต้องปิด client เมื่อเลิกใช้ HTTP client จะคืน connection เองเมื่อ object ถูกทำลาย หรืออย่างช้าที่สุดตอน process ของ PHP จบ

<a id="error-handling"></a>

## การจัดการ error

exception ทุกตัวที่ SDK โยนเป็น `ThunderError` ซึ่งสืบทอดจาก `RuntimeException` และอยู่ใน namespace `Thunder\Exception` ทั้งหมด ส่วน exception ที่มาจาก API เป็น `ApiError` ซึ่งมี `status`, `code` (เช่น `SLIP_NOT_FOUND`), `getMessage()`, `requestId` และ `retryAfter` บางคลาสเป็นคลาสย่อยของอีกคลาส ได้แก่ `QuotaExceededError` เป็น `PermissionDeniedError` ส่วน `InvalidImageError`, `InvalidUrlError` และ `DuplicateSlipError` เป็น `InvalidRequestError` ส่วน `SlipNotFoundError` และ `SlipPendingError` เป็น `NotFoundError` ส่วน `ServiceUnavailableError` เป็น `ServerError` และ `RequestTimeoutError` เป็น `NetworkError`

| คลาส exception | HTTP status | เกิดเมื่อ | ควรทำอย่างไร |
| --- | --- | --- | --- |
| `AuthenticationError` | 401 | ไม่มี API key, key ผิด หรือ key ถูกยกเลิกแล้ว | ตรวจ key ที่ส่งเข้าไปหรือ `THUNDER_API_KEY` ถ้าจำเป็นให้สร้าง key ใหม่ใน developer portal |
| `PermissionDeniedError` | 403 | key ถูกต้องแต่ไม่มีสิทธิ์ทำคำขอนี้ เช่น สาขาถูกปิด แพ็กเกจหมดอายุ หรือ IP ของเซิร์ฟเวอร์ไม่อยู่ในรายการที่อนุญาต | ดู `$error->code` แล้วแก้การตั้งค่านั้นใน developer portal |
| `QuotaExceededError` | 403 | โควต้าหมด คำขอนี้ยังไม่ถูกหักโควต้า | เติมโควต้าหรือเปลี่ยนแพ็กเกจ แล้วส่งคำขอใหม่ |
| `InvalidRequestError` | 400 | API ไม่รับค่าบางอย่างในคำขอ เช่น ข้อความคิวอาร์หรือค่าตัวเลือก | แก้ค่าที่ `$error->getMessage()` บอก ส่งคำขอเดิมซ้ำจะได้ error เดิม |
| `InvalidImageError` | 400 | อ่านคิวอาร์ของสลิปในรูปไม่ได้ ชนิดไฟล์ไม่รองรับ หรือไฟล์ใหญ่เกินไป | ขอให้ลูกค้าส่งรูป JPEG, PNG, GIF หรือ WebP ที่ชัดและไม่เกิน 4 MB |
| `InvalidUrlError` | 400 | URL ของรูปหรือ `callbackUrl` ชี้ไปที่ที่อยู่ภายใน ไม่ได้ใช้ http หรือ https หรือเปิดไม่ได้ | ใช้ URL แบบ https ที่เปิดได้จากภายนอก |
| `DuplicateSlipError` | 400 | เฉพาะ API v1 ที่ใส่ `checkDuplicate: true`: สลิปนี้เคยถูกตรวจแล้ว `$error->slip` คือผลการตรวจครั้งแรก | ถือว่าได้รับยอดนี้ไปแล้ว อย่ายืนยันซ้ำ |
| `NotFoundError` | 404 | ไม่พบบัญชีธนาคาร งาน หรือรายการที่ขอ | ตรวจ ID ที่ส่งเข้าไป |
| `SlipNotFoundError` | 404 | ธนาคารไม่มีรายการโอนที่ตรงกับสลิปนี้ | อย่ารับยอด และขอสลิปตัวจริงจากลูกค้า |
| `SlipPendingError` | 404 | ธนาคารยังไม่เผยแพร่รายการโอนนี้ มักเกิดกับสลิปธนาคารกรุงเทพที่เพิ่งโอน | รอประมาณหนึ่งนาทีแล้วตรวจใหม่ |
| `ConflictError` | 409 | บัญชีรับเงินที่กำลังเพิ่มมีอยู่แล้ว | ใช้บัญชีเดิม หาได้ด้วย `bankAccounts->iterateAll()` |
| `RateLimitError` | 429 | ส่งคำขอถี่เกินไป `$error->retryAfter` บอกว่าต้องรอกี่วินาที | ลดจำนวนคำขอที่ส่งพร้อมกัน SDK ลองใหม่ให้แล้วก่อนโยน exception นี้ |
| `ServerError` | 500, 502, 504; 5xx อื่นยกเว้น 503 | เกิดปัญหาที่ฝั่ง Thunder | ลองใหม่ภายหลัง ถ้ายังเกิดซ้ำให้ติดต่อซัพพอร์ตพร้อม `$error->requestId` |
| `ServiceUnavailableError` | 503 | บริการไม่พร้อมใช้งานชั่วคราว เช่น ช่วงต่ออายุโควต้า | ลองใหม่ในอีกไม่กี่นาที |
| `ApiError` | 4xx อื่น ๆ | API ตอบ error ด้วย status ที่ไม่มีคลาสเฉพาะ | เก็บ log `status`, `code` และ `requestId` ถ้ายังเกิดอยู่ให้ติดต่อซัพพอร์ต |
| `NetworkError` | เครือข่าย | ไม่ได้รับคำตอบเพราะ DNS การเชื่อมต่อ หรือ TLS ล้มเหลว | ตรวจเครือข่ายหรือ proxy แล้วลองใหม่ |
| `RequestTimeoutError` | เครือข่าย | ไม่ได้รับคำตอบภายใน `timeout` | ลองใหม่หรือเพิ่ม `timeout` การตรวจสลิปครั้งนั้นอาจถูกนับโควต้าไปแล้ว |
| `InputError` | ก่อนส่งคำขอ | SDK เจอปัญหาก่อนส่ง เช่น ไม่มี API key ข้อความคิวอาร์ว่าง รูปเกิน 4 MB หรือไฟล์ไม่ใช่รูป `$error->code` บอกสาเหตุ | แก้ค่าที่ส่งเข้าไป |
| `WebhookSignatureError` | ก่อนส่งคำขอ | เว็บฮุคไม่มีลายเซ็น หรือลายเซ็นไม่ตรง | ตอบเว็บฮุคนั้นด้วย status 400 |
| `JobWaitTimeoutError` | ก่อนส่งคำขอ | `waitForJob()` รอจนครบ `timeout` แล้ว `$error->lastJob` คือสถานะล่าสุดที่เห็น | รอนานขึ้น หรือรอผลทางเว็บฮุคแทน |
| `BatchSubmitError` | ก่อนส่งคำขอ | `submitBankMany()` หยุดกลางทาง `$error->submitted` คือชุดที่ส่งไปแล้ว `$error->failedChunk` คือลำดับของชุดที่ล้มเหลว (นับจาก 0) และ `$error->cause` คือ exception ของชุดนั้น | ส่งชุดที่ล้มเหลวและชุดถัดไปใหม่ ชุดที่ส่งไปแล้วยังทำงานต่อ |

ตัวอย่างนี้ตรวจสลิปที่ลูกค้าอัปโหลดตอนชำระเงิน แล้วแปลง exception แต่ละแบบเป็นข้อความที่แสดงให้ลูกค้าเห็น

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Thunder;
use Thunder\Exception\ApiError;
use Thunder\Exception\InvalidImageError;
use Thunder\Exception\QuotaExceededError;
use Thunder\Exception\RateLimitError;
use Thunder\Exception\SlipNotFoundError;
use Thunder\Exception\SlipPendingError;
use Thunder\Input\FileInput;
use Thunder\Input\VerifyOptions;

function checkPayment(Thunder $thunder, string $uploadedFile, float $orderTotal): string
{
    try {
        $result = $thunder->verify->bankByImage(FileInput::fromPath($uploadedFile), new VerifyOptions(
            matchAccount: true,
            matchAmount: $orderTotal,
            checkDuplicate: true,
        ));
        if ($result->isDuplicate) {
            return 'สลิปนี้เคยใช้แล้ว';
        }
        if (!$result->isAmountMatched) {
            return "สลิปนี้โอน {$result->amountInSlip} บาท ยอดไม่ตรงกับออเดอร์";
        }
        if ($result->matchedAccount === null) {
            return 'สลิปนี้ไม่ได้โอนเข้าบัญชีของร้าน';
        }
        return 'ได้รับชำระเงินแล้ว';
    } catch (SlipNotFoundError) {
        return 'ไม่พบรายการโอนนี้ที่ธนาคาร';
    } catch (SlipPendingError) {
        return 'ธนาคารยังประมวลผลรายการนี้อยู่ ลองใหม่ในอีกหนึ่งนาที';
    } catch (InvalidImageError) {
        return 'กรุณาอัปโหลดรูปหรือภาพหน้าจอสลิปที่ชัดเจน';
    } catch (QuotaExceededError | RateLimitError $error) {
        error_log("Thunder limit reached: {$error->code} {$error->requestId}");
        return 'ตอนนี้ยังตรวจสลิปไม่ได้ กรุณาลองใหม่อีกครั้ง';
    } catch (ApiError $error) {
        error_log("Thunder error: {$error->status} {$error->code} {$error->requestId}");
        return 'ตรวจสลิปไม่สำเร็จ กรุณาลองใหม่';
    }
}

echo checkPayment(new Thunder(), __DIR__ . '/slip.jpg', 1500), "\n";
```

### SDK ลองใหม่ให้เมื่อไร

SDK ลองส่งคำขอที่ล้มเหลวใหม่ได้สูงสุด `maxRetries` ครั้ง (ค่าเริ่มต้น 2 ครั้ง) ก่อนลองใหม่ครั้งแรกจะรอประมาณครึ่งวินาที แล้วเพิ่มเวลารอเป็นสองเท่าทุกครั้ง ไม่เกิน 8 วินาที ถ้า API ส่ง `Retry-After` มา SDK จะรอตามนั้นแทน แต่ถ้าต้องรอนานกว่า 60 วินาทีจะโยน exception ทันที

| คำขอ | ลองใหม่เมื่อ | ไม่ลองใหม่เมื่อ |
| --- | --- | --- |
| การอ่านข้อมูลและการผูกบัญชี: `getInfo()`, `banks->list()`, `bankAccounts->list()`, `listAll()`, `get()` และ `link()`, `jobs->get()`, `v1->getMe()`, `v1->generateQr()` | เครือข่ายล้มเหลว หมดเวลา หรือ API ตอบ 429, 500, 502, 503 หรือ 504 | API ตอบ 400, 401, 403, 404 หรือ 409 |
| การตรวจสลิปและการส่งงานเบื้องหลัง ซึ่งใช้โควต้า | API ตอบ 429 หรือเชื่อมต่อไม่สำเร็จก่อนคำขอจะออกจากเซิร์ฟเวอร์ | คำขอส่งออกไปแล้ว แล้วหมดเวลาหรือได้คำตอบ 5xx |
| การแก้ไขบัญชีรับเงิน: `create()`, `update()` และ `unlink()` | API ตอบ 429 หรือเชื่อมต่อไม่สำเร็จก่อนคำขอจะออกจากเซิร์ฟเวอร์ | คำขอส่งออกไปแล้ว แล้วหมดเวลาหรือได้คำตอบ 5xx |

การตรวจสลิปที่ส่งถึง API แล้วอาจถูกนับโควต้าไปแล้ว แม้จะไม่ได้รับคำตอบกลับมา การลองใหม่อาจทำให้ถูกหักโควต้าซ้ำ SDK จึงให้คุณเป็นคนตัดสินใจเอง

<a id="webhooks"></a>

## เว็บฮุค

เมื่องานเบื้องหลังเสร็จ Thunder จะส่งคำขอ POST ไปที่ `callbackUrl` ของคุณ ถ้าสาขาตั้ง Webhook Secret ไว้ คำขอจะมี header `X-Thunder-Signature` ให้ตรวจลายเซ็นกับ body ดิบก่อนแปลงหรือเชื่อข้อมูลใด ๆ ในนั้น

ตัวรับเว็บฮุคนี้เป็น PHP ธรรมดา PHP เก็บ header `X-Thunder-Signature` ไว้ใน `$_SERVER['HTTP_X_THUNDER_SIGNATURE']`

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Thunder\Exception\InputError;
use Thunder\Exception\WebhookSignatureError;
use Thunder\Webhook;

$rawBody = (string) file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_THUNDER_SIGNATURE'] ?? null;
$secret = (string) getenv('THUNDER_WEBHOOK_SECRET');

try {
    $event = Webhook::constructEvent($rawBody, is_string($signature) ? $signature : null, $secret);
} catch (WebhookSignatureError | InputError) {
    http_response_code(400);
    echo 'invalid webhook';
    exit;
}

if ($event->status === 'success') {
    error_log("ตรวจผ่าน {$event->jobId} {$event->data?->rawSlip->transRef} {$event->data?->amountInSlip}");
} else {
    error_log("ตรวจไม่ผ่าน {$event->jobId} {$event->status} {$event->error?->code}");
}
echo 'ok';
```

ถ้าใช้ Laravel ให้ส่ง `$request->getContent()` เป็น body ดิบ และ `$request->header(Webhook::SIGNATURE_HEADER)` เป็นลายเซ็น โดยใช้ route ที่ไม่มีการป้องกัน CSRF เช่น route ใน `routes/api.php` อย่าแปลง JSON แล้วแปลงกลับเป็นข้อความก่อนตรวจ เพราะ bytes จะเปลี่ยน แล้วลายเซ็นจะไม่ตรง

| เมธอด | ใช้ทำอะไร |
| --- | --- |
| `Webhook::constructEvent($rawBody, $signature, $secret)` | ตรวจลายเซ็นแล้วคืน `WebhookEvent` ที่แปลงแล้ว โยน `WebhookSignatureError` เมื่อไม่มีลายเซ็นหรือลายเซ็นไม่ตรง และโยน `InputError` เมื่อ body ไม่ใช่ event ที่ถูกต้อง |
| `Webhook::verifySignature($rawBody, $signature, $secret)` | คืน `true` เมื่อลายเซ็นตรง และ `false` เมื่อไม่ตรง |
| `Webhook::parseEvent($rawBody)` | แปลง event โดยไม่ตรวจลายเซ็น ใช้หลังจากตรวจลายเซ็นเองแล้วเท่านั้น |

`$secret` คือ Webhook Secret ของสาขา ตอบกลับด้วย status 2xx ภายใน 5 วินาที Thunder อาจส่ง event เดิมมาซ้ำได้ ให้ใช้ `jobId` ข้าม event ที่จัดการไปแล้ว

## ความปลอดภัยและข้อมูลส่วนบุคคล

- เก็บ API key ไว้ฝั่งเซิร์ฟเวอร์เท่านั้น อย่าใส่ในหน้าเว็บ แอปมือถือ หรือ repository สาธารณะ เพราะใครได้ key ไปก็ใช้โควต้าของคุณได้
- อ่าน key จาก `THUNDER_API_KEY` หรือไฟล์ secret แทนการเขียนลงในโค้ด
- SDK ไม่ใส่ API key ลงในข้อความ exception และ `var_dump()` ของ client ก็ไม่แสดง key จึงเก็บ log ของ error ได้อย่างปลอดภัย
- สลิปมีชื่อ เลขบัญชี และยอดเงิน ซึ่งเป็นข้อมูลส่วนบุคคล เก็บเฉพาะที่จำเป็น ดูแลเหมือนข้อมูลลูกค้าอื่น ๆ และปฏิบัติตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA)
- ตรวจลายเซ็นของเว็บฮุคทุกครั้งก่อนนำข้อมูลไปใช้

## เวอร์ชันและนโยบายการรองรับ

- การออกเวอร์ชันใช้ [semantic versioning](https://semver.org) เวอร์ชัน minor และ patch จะไม่ทำให้โค้ดเดิมพัง การเปลี่ยนแปลงที่กระทบโค้ดเดิมจะออกเป็นเวอร์ชัน major ใหม่ พร้อมวิธีอัปเกรดใน changelog
- SDK รองรับ PHP เวอร์ชันที่โปรเจกต์ PHP ยังออก security fix ให้ เมื่อ PHP เวอร์ชันใดหมดอายุการดูแล SDK เวอร์ชันถัดไปอาจเลิกรองรับเวอร์ชันนั้น และจะแจ้งไว้ใน changelog
- [CHANGELOG.md](CHANGELOG.md) และ[หน้า releases](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/releases) รวบรวมทุกเวอร์ชันและสิ่งที่เปลี่ยน

## ตัวอย่างและเอกสาร

- โฟลเดอร์ [`examples`](examples) มีโปรแกรมที่รันได้จริงครบทุกเมธอด clone repository นี้ รัน `composer install` แล้วรันตัวอย่าง เช่น `THUNDER_API_KEY="your-api-key" php examples/verify-bank-image.php slip.jpg` ถ้าไม่ใส่ argument ตัวอย่างจะบอกวิธีเรียกที่ถูกต้อง
- [คู่มือ SDK](https://document.thunder.in.th/th/sdk/php) อธิบาย SDK นี้ในเอกสารของ Thunder
- [เอกสาร API](https://document.thunder.in.th) อธิบายทุก endpoint ทุกฟิลด์ และ[รหัส error](https://document.thunder.in.th/th/reference/error-codes)

## ช่วยเหลือ

เรื่องบัญชี โควต้า หรือการชำระเงิน ติดต่อผ่าน[หน้าติดต่อเรา](https://developer.thunder.in.th/contact) พร้อม request id ถ้ามี แจ้งบั๊กหรือขอฟีเจอร์ได้ใน [issues](https://github.com/THUNDER-SOLUTION-CO-LTD/sdk-php/issues) ห้ามแนบ API key หรือรูปสลิปจริง

## ร่วมพัฒนา

อ่านวิธีติดตั้งเครื่องมือ รันทดสอบ และส่ง pull request ใน [CONTRIBUTING.md](CONTRIBUTING.md) ผู้ดูแลการออกเวอร์ชันอ่าน [RELEASING.md](RELEASING.md)

## แจ้งปัญหาความปลอดภัย

แจ้งแบบส่วนตัวตาม [SECURITY.md](SECURITY.md) หรือติดต่อผ่าน[หน้าติดต่อ](https://developer.thunder.in.th/contact) ห้ามแจ้งช่องโหว่ใน issue สาธารณะ

## สัญญาอนุญาต

[MIT](LICENSE). Copyright (c) 2026 Thunder Solution Co., Ltd.
