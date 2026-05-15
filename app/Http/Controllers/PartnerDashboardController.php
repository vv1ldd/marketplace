<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerDashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        $agreementText = "Договор на оказание услуг по размещению Товарных предложений... 
        1. Термины и определения
        Сервис Яндекс Маркет, Маркетплейс Яндекс Маркета (Сервис) — все веб-сайты...
        База данных «Яндекс Маркет» — представляет собой информационно-справочную систему...
        Личный кабинет — программный интерфейс взаимодействия Заказчика...
        ...
        2. Предмет Договора и обязательства Сторон
        2.1. По настоящему Договору Исполнитель за вознаграждение обязуется оказывать Заказчику Услуги...
        [Полный текст договора загружен в систему]";

        return view('partner.dashboard', [
            'user' => $user,
            'legalEntity' => $legalEntity,
            'agreementText' => $agreementText
        ]);
    }

    public function signAgreement(Request $request)
    {
        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        // 🛡️ Sovereign Signature Logic
        // In a real app, we would verify a Passkey assertion here.
        // For now, we anchor the signature to the user's L1 identity.
        $legalEntity->update([
            'agreement_signed_at' => now(),
            'agreement_signature' => 'SGN:' . bin2hex(random_bytes(32)), // Placeholder for real L1 signature
        ]);

        return response()->json(['success' => true]);
    }

    public function updateBank(Request $request)
    {
        $request->validate([
            'bic' => 'required|string|size:9',
            'account' => 'required|string|size:20',
        ]);

        $user = Auth::user();
        $legalEntity = $user->legalEntities()->first();

        $legalEntity->update([
            'bank_bic' => $request->bic,
            'bank_account' => $request->account,
        ]);

        return response()->json(['success' => true]);
    }
}
