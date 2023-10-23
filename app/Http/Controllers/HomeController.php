<?php namespace App\Http\Controllers;
ini_set('memory_limit', '-1');
set_time_limit(0);
setlocale(LC_ALL, 'ru_RU.utf8');
date_default_timezone_set( 'Europe/Moscow' );

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Http\Controllers\MuzController;
use App\Http\Controllers\BtxController;

use App\Http\Controllers\SyncController;

use App\Models\Classes;
use App\Models\Corpuses;
use App\Models\Orders;
use App\Models\Users;
//use \Crest;

class HomeController extends Controller{

public function in(Request $request){
    $mb = new MuzController;
    $bx = new BtxController;
    
///* / ЕСЛИ ПРИЛОЖЕНИЕ ОТКРЫТО ИЗ СДЕЛКИ БЕРЁМ ID СДЕЛКИ ///* /
    $OpenDealId = empty($request->dealId) ? (
            (!empty($request->PLACEMENT_OPTIONS) && !empty($dealJson = json_decode($request->PLACEMENT_OPTIONS, true))) ? 
                (empty($dealJson['ID']) ? null : $dealJson['ID']) : null
        ) : $request->dealId;
///* / БЕРЁМ КОРПУСА ИЗ БАЗЫ ///* /
    if($Corpuses = (!empty($Corpuses = Corpuses::all()->toArray())) ? $Corpuses : null){
        $week = ['пн','вт','ср','чт','пт','сб','вс'];
        $month = ['Январь', 'Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь', 'Ноябрь','Декабрь'];
        $monthShort = ['Янв', 'Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт', 'Ноя','Дек'];
        $curBaseImg = ['https://partner.musbooking.com/res/bases/220315-1650-2.jpeg','https://partner.musbooking.com/res/bases/220314-2123-5.jpeg',];
///* / БЕРЁМ ИЗ ПАРАМЕТРОВ ВРЕМЕННУЮ МЕТКУ ЛИБО ФОРМИРУЕМ ЕЁ ИЗ ТЕКУЩЕГО ВРЕМЕНИ ///* /        
        $curTimestamp           = (empty($request->dateIn)) ? time() : strtotime($request->dateIn); 
///* / ФОРМИРУЕМ ДАТ ИЗ ВРЕМЕННОЙ МЕТКИ ///* /        
        $curDate['day']         = date('d', $curTimestamp);
        $curDate['month']       = date('m', $curTimestamp);
        $curDate['monthRus']    = $month[$curDate['month']-1];
        $curDate['year']        = date('Y', $curTimestamp);
        $curDate['week']        = $week[date('N', $curTimestamp)-1];
        $curDate['calendar']    = $curDate['week'].', '.$monthShort[$curDate['month']-1].' '.$curDate['day'];
        $curDate['rus']         = $curDate['day'].' '.$curDate['monthRus'].' '.$curDate['year'];
        $curDate['datestamp']   = $curDate['year'].'-'.$curDate['month'].'-'.$curDate['day'];//strtotime($curDate['year'].'-'.$curDate['month'].'-'.$curDate['day']);
///* / БЕРЁМ ТЕКУЩИЙ КОРПУС ИЗ МАССИВА КОРПУСОВ УЧИТЫВАЯ ПАРАМЕТР ЗАПРОСА КОРПУСА ///* /       
        $curCorpuses = $Corpuses[(!empty($request->baseId)) ? array_search($request->baseId, array_column($Corpuses, 'muzid')) : 0]; 

        
///* / ГЕНЕРИРУЕМ МАССИВ С РАБОЧИМ ВРЕМЕНЕМ КОРПУСА ///* /                
        if(!empty($curCorpuses['workfrom']) && !empty($curCorpuses['workto'])){
            $curCorpuses['workfrom'] = (0 === $curCorpuses['workfrom']) ? 1 : $curCorpuses['workfrom'];
            $curCorpusesPeriod = new \DatePeriod(new \DateTime(gmdate('H:i', $curCorpuses['workfrom']*3600)), new \DateInterval('PT1H'), new \DateTime(gmdate('H:i', $curCorpuses['workto']*3600-61)));
            $curTimes = []; foreach($curCorpusesPeriod as $period){$curTimes[] = $period->format('H:i');} $curTimes[] =  $curCorpuses['workto'].':00';
        }else{
            $curTimes = ['01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '24:00'];            
        }
///* / БЕРЁМ КЛАССЫ ДЛЯ КОРПУСА ИЗ БАЗЗЫ ///* /               
        if($Classes = (!empty($curCorpuses['muzid']) && !empty($Classes = Classes::where('corpusesid', $curCorpuses['muzid'])->get()->toArray())) ?  $Classes : null){
        //usort($clases, function($a, $b){return $a['order'] <=> $b['order'];});//pa($Corpuses); exit; pa($curCorpuses);

///* / ПЕРЕБИРАЕМ МАССИВ КЛАСОВ ИЗ БАЗЫ ///* /
            foreach($Classes as $Class){
 //* / ФОРМИРУЕМ МАССИВ ЗАКАЗОВ В КЛАССЕ ///* /
                $orders[$Class['muzid']] = (is_array($Orders = Orders::where('classesid', $Class['id'])->whereDate('datefrom', $curDate['datestamp'])->get()->toArray()) && !empty($Orders)) ? $Orders : null;
                //pa($clase); exit; $dayAfter = (new DateTime('2014-07-10'))->modify('+1 day')->format('Y-m-d'); //pa($orders);exit; //pa($curDate['datestamp']); pa($Class); exit;
//
/* / ВЫБИРАЕМ ТОВАР ИЗ БИТРИКСА ЕСЛИ ЕСТЬ ///* /         
            try{$bxProductId = $bx->bx24->getProduct($Class['product'])['ID'];}catch(\Exception $e){
                $bxProductId = (str_replace(['"', '}','{', '.', ','], '', explode(':', $e->getMessage())[6]));
            }
///*/// ВЫБИРАЕМ МАССИВ ЦЕННИКОВ ИЗ РЕСУРСОВ ///* /
                $prices = include(resource_path('arrays/prices.php'));
                $curPrice = $prices[(0 < $key = array_search($Class['muzid'], array_column($prices, 'muzid'))) ? $key : 0]['price'];            
//
/* / ЕСЛИ НЕТ ТОВАРА В БИТРИКСЕ, СОЗДАЁМ ТОВАР С ЦЕНОЙ КЛАССА ///* / 
            if('Product is not found' == $bxProductId || 'ID is not defined or invalid' == $bxProductId){
                $bxProductId = $bx->bx24->addProduct([
                    'NAME' => $Class['name'], 
                    'CURRENCY_ID' => 'RUB', 
                    'PRICE' => $curPrice,
                ]);
            }
 */           
        } 
///*/// СОЗДАЁМ МАССИВ ЗАГЛУШКУ С КЛАССАМИ И ВРЕМЕНЕМ РАБОТЫ ДЛЯ ЗАКАЗОВ ///* /        
            $curOrders = array_fill_keys($curTimes, $rooms = array_fill_keys(array_keys($orders), ''));
        //pa($curOrders); exit;

///* / ПЕРЕБИРАЕМ МАССИВ ЗАКАЗОВ И РАСКЛАДЫВАЕМ В МАССИВ СО ВРЕМЕНЕМ ///* /        
            foreach($orders as $classId => $ordersList){
///* / ВЫБИРАЕМ ID КЛАССА ИЗ НАШЕЙ БД ///* /
                if(!empty($ordersList)){ //$cl = Classes::where('muzid', '=', $id); pa($cl, 5); exit;//pa($classId); exit; //pa($ordersList); exit;
///* / ВЫБИРАЕМ ВСЕХ ПОЛЬЗОВАТЕЛЕЙ ИЗ КЛАССА СДЕЛАВШИХ ЗАКАЗ В ТЕКУЩЕЙ ДАТЕ ///* /                
                    $users = Users::whereIn('id', array_values(array_unique(array_column($ordersList, 'usersid'), SORT_NUMERIC)))->get()->toArray();
 ///* / ПЕРЕБИРАЕМ ЗАКАЗЫ ИЗ БАЗЫ ///* /
                    for($i=0,$c=count($ordersList); $i<$c; $i++){
///* / ЦЕПЛЯЕМ К ЗАКАЗУ ПОЛЬЗОВАТЕЛЯ ///* /
                        $ordersList[$i]['users'] = (0 === $keyUser = array_search($ordersList[$i]['usersid'], array_column($users, 'id'))) ? $users[0] : 
                                                   ((is_numeric($keyUser) && $keyUser > 0) ? $users[$keyUser] : []);
                        //pa($ordersList[$i]);exit;
///* / ЕСЛИ В ЗАКАЗЕ ЕСТЬ ИМЯ И ТЕЛЕФОН ///* /
                        if(!empty($phone = $ordersList[$i]['users']['phone']) && !empty($fio = $ordersList[$i]['users']['fio'])){
//
/* / ПРОВЕРЯЕМ ЕСТЬ ЛИ ПОЛЬЗОВАТЕЛЬ В БИТРИКС ПО ТЕЛЕФОНУ, ЕСЛИ НЕТ ///* /
                            if(empty($bxContactIdCheck = $bx->bx24->getContactsByPhone($phone))){
                                list($lastname, $firstname) = explode(' ', trim($fio));
///* / СОЗДАЁМ ПОЛЬЗОВАТЕЛЯ В БИТРИКС ///* /                            
                                $bxContactId = $bx->bx24->addContact([
                                    'NAME'      => $firstname,
                                    'LAST_NAME' => $lastname,
                                    'PHONE'     => array((object)['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']),
                                    'EMAIL'     => array((object)['VALUE' => $email]),
                                    //'COMPANY_ID'  => 332,
                                    //'SECOND_NAME' => 'Васильевич',
                                ]);
///* / ЕСЛИ ПОЛЬЗОВАТЕЛЬ БИТРИКС СУЩЕСТВУЕТ БЕРЁМ ЕГО ID ///* /                            
                            }else{$bxContactId = $bxContactIdCheck[0]['ID'];}
 */
                        }else{
///* / ЕСЛИ В ЗАКАЗЕ НЕТ ИМЯ И ТЕЛЕФОН ///* /
                            $importContact = $bx->bx24->getContact(SyncController::DEFAULT_CONTACT);
                            $phone = (empty($importContact['PHONE'][0]['VALUE'])) ? null : $importContact['PHONE'][0]['VALUE'];
                            $email = (empty($importContact['EMAIL'][0]['VALUE'])) ? null : $importContact['EMAIL'][0]['VALUE'];
                            $fio = $importContact['NAME'];
                            $bxContactId = $importContact['ID'];                        

                            $ordersList[$i]['users'][id] = 0;
                            $ordersList[$i]['users'][bitrixid] = $bxContactId;
                            $ordersList[$i]['users'][fio] = $fio;
                            $ordersList[$i]['users'][phone] = $phone;
                            $ordersList[$i]['users'][email] = $email;
                        }                    
//
/* / ВЫБИРАЕМ СДЕЛКУ ИЗ БИТРИКСА ЕСЛИ ЕСТЬ ///* /
                    try{$dealId = $bx->bx24->getDeal($ordersList[$i]['deal'], [\App\Bitrix24\Bitrix24API::$WITH_PRODUCTS, \App\Bitrix24\Bitrix24API::$WITH_CONTACTS]);}catch(\Exception $e){
                        $dealId = str_replace(['"', '}','{', '.', ','], '', (explode('.', explode(':', $e->getMessage())[11])[0]));
                    }
///*/// ФОРМИРУЕМ ВРЕМЕННОЙ ПЕРИОД ЗАКАЗА И КОЛИЧЕСТВО ТОВАРОВ ДЛЯ БИТРИКС СДЕЛКИ ///*/                       
                            $periodsOrder = new \DatePeriod(new \DateTime(date('H:i', strtotime($ordersList[$i]['datefrom']))), new \DateInterval('PT1H'), new \DateTime(date('H:i', strtotime($ordersList[$i]['dateto'])))); 
                            $quantity = 0;
                            foreach($periodsOrder as $periodOrder){$quantity++;
                                $curOrders[$periodOrder->format('H:00')][$classId] = $ordersList[$i];
                            }
//
/* / ЕСЛИ НЕТ СДЕЛКИ В БИТРИКСЕ, СОЗДАЁМ СДЕЛКУ ///* /
                        if('ID is not defined or invalid' == $dealId){
                            $title = ((!empty($Classes[$classId]['name'])) ? '('.$Classes[$classId]['name'].') ' : '').
                                     ((!empty($ordersList[$i]['users'][fio])) ? $ordersList[$i]['users'][fio].' ' : '').
                                     ((!empty($ordersList[$i]['comment'])) ? $ordersList[$i]['comment'] : '');
                            $title = (!empty($title)) ? $title : 'Безымянная - '.time();
                            
                            $bx->bx24->setDealProductRows($dealId = $bx->bx24->addDeal([
                                'TITLE' => $title, 
                                'CONTACT_ID' => $bxContactId, 
                                SyncController::MUZ_ID_BX => $ordersList[$i]['muzid'], 
                                SyncController::DATE_FROM_BX => $ordersList[$i]['datefrom'], 
                                SyncController::DATE_TO_BX => $ordersList[$i]['dateto'], 
                            ]), [[ 'PRODUCT_ID' => $Classes[$classId]['product'], 'PRICE' => $Classes[$classId]['price'], 'QUANTITY' => $quantity ]]);
                        }
 */
    }}}}

    
    if('order' == $request->add){
        $arDate = explode(' ', trim($request->date));
        $day        = (!empty($arDate[0])) ? $arDate[0] : '';
        $month      = (0 <= ($keyMonth = array_search($arDate[1], $month))) ? $keyMonth+1 : '';
        $year       = (!empty($arDate[2])) ? $arDate[2] : '';
        $date       = $year.'-'.$month.'-'.$day;
        
        foreach(json_decode($request->times, true) as $arDatetime){if($d = is_date($date.' '.$arDatetime)){$Datetime[] = (array) $d;}}

        //$addOrderNotification = [];
        $addOrderClass = $Classes[(0 <= $keyClass = array_search($request->classid, array_column($Classes, 'muzid'))) ? $keyClass : ''];
        if(empty($addOrderClass['muzid'])){      $addOrderNotification []='Нет доступа к классу';}
        if(empty($request->date)){               $addOrderNotification []= 'Не выбрана дата';}
        if(empty($request->times)){              $addOrderNotification []='Не выбрано время';}
        if(empty($request->name)){               $addOrderNotification []='Не введено поле имя';}
        if(empty($request->sename)){             $addOrderNotification []='Не введено поле фамилия';}
        if(empty($request->phone)){              $addOrderNotification []='Не введено поле телефон';}
        if(empty($request->email)){              $addOrderNotification []='Не введено поле email';}
        
        //pa($addOrderNotification);
        
        if(empty($addOrderNotification) && !empty($Datetime)){
            /*
            $validated = $request->validate([
                'title' => 'required|unique:posts|max:255',
                'body' => 'required',
            ]);
            */
            $mb->__construct();
            foreach ($Datetime as $k => $v){
                pa($mb->syncAdd($addOrderClass['muzid'], $v['date'], $request->name, $request->sename, $request->phone,$request->email));
            }
            
        }
        
    }

///*/-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------///*/

///*/ Вывод ///*/
        $data = get_defined_vars(); unset($data['request'], $data['bx'], $data['mb']); $data = array_keys($data);
        return view('front.home', compact($data));























        

    }else{abort(500);}
    
}


public function index(Request $request){
    $mb = new MuzController; //$api->setLogin(); 
    $bx = new BtxController; 

    if('stoimost' == $request->api){$mb->stoimost($request);exit;}
        
    $dealId = empty($request->dealId) ? (
            (!empty($request->PLACEMENT_OPTIONS) && !empty($dealJson = json_decode($request->PLACEMENT_OPTIONS, true))) ? 
                (empty($dealJson['ID']) ? null : $dealJson['ID']) : null
        ) : $request->dealId;

    if($bases = (is_array($bases = $mb->listBases())) ? $bases : null){
        //
        /*/ Синхранизация корпусов pa($bases); exit; ///
        foreach($bases as $base){
            $newBase = Corpuses::firstOrCreate(['muzid' => $base['id']]);
            $newBase->muzid = $base['id'];
            $newBase->name = $base['value'];
            $newBase->type = $base['sphere'];
            $newBase->save();
        }
        */
        $week = ['пн','вт','ср','чт','пт','сб','вс'];
        $month = ['Январь', 'Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь', 'Ноябрь','Декабрь'];
        $monthShort = ['Янв', 'Фев','Мар','Апр','Май','Июн','Июл','Авг','Сен','Окт', 'Ноя','Дек'];
        $curBaseImg = ['https://partner.musbooking.com/res/bases/220315-1650-2.jpeg','https://partner.musbooking.com/res/bases/220314-2123-5.jpeg',];
        
        $curTimestamp           = (empty($_GET['dateIn'])) ? time() : strtotime($_GET['dateIn']); 
        $curDate['day']         = date('d', $curTimestamp);
        $curDate['month']       = date('m', $curTimestamp);
        $curDate['monthRus']    = $month[$curDate['month']-1];
        $curDate['year']        = date('Y', $curTimestamp);
        $curDate['week']        = $week[date('N', $curTimestamp)-1];
        $curDate['calendar']    = $curDate['week'].', '.$monthShort[$curDate['month']-1].' '.$curDate['day'];
        $curDate['rus']         = $curDate['day'].' '.$curDate['monthRus'].' '.$curDate['year'];
        $curDate['timestamp']   = strtotime($curDate['year'].'-'.$curDate['month'].'-'.$curDate['day']);
        
        $curBaseKey = (!empty($request->baseId) && !empty($bases)) ? array_search($request->baseId, array_column($bases, 'id')) : null;
        $curBase = (empty($curBaseKey)) ? ((!empty($bases[0]['value']) && !empty($bases[0]['sphere'])) ? $bases[0] : null) : $bases[$curBaseKey]; 
        $curBase['workHours'][0]['from'] = (0 === $curBase['workHours'][0]['from']) ? 1 : $curBase['workHours'][0]['from'];
        //pa($curBase['id']); //exit;
        $clases = (!empty($curBase['id'])) ? array_filter($mb->listClases(), function($el) use($curBase){return $el['baseId'] === $curBase['id'];}) : null;
        usort($clases, function($a, $b){return $a['order'] <=> $b['order'];});
        //pa($clases); exit;
        if(!empty($curBase['workHours'][0]['from']) && !empty($curBase['workHours'][0]['to'])){
            $period = new \DatePeriod(new \DateTime(gmdate('H:i', $curBase['workHours'][0]['from']*3600)), new \DateInterval('PT1H'), new \DateTime(gmdate('H:i', $curBase['workHours'][0]['to']*3600-61)));
            $curTimes = []; foreach($period as $value){$curTimes[] = $value->format('H:i');} $curTimes[] =  $curBase['workHours'][0]['to'].':00';
        }else{
            $curTimes = ['01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00', '24:00'];            
        }
 ///*/ ПЕРЕБИРАЕМ МАССИВ КЛАСОВ ИЗ МУЗБУКИНГА ///* /
        foreach($clases as $clase){
 //* / ФОРМИРУЕМ МАССИВ ЗАКАЗОВ В КЛАССЕ ///* /
            $orders[$clase['id']] = $mb->listOrders($clase['id'], date('Y-m-d', $curDate['timestamp']));
 
///* / СОЗДАЁМ ИЛИ ВЫБИРАЕМ КЛАСС ИЗ НАШЕЙ БАЗЫ ПО ID МУЗБУКИНГА ///* /
            //pa($clase); exit;
            $prices = include(resource_path('arrays/prices.php'));
            
            $newClass = Classes::firstOrCreate(['muzid' => $clase['id']]);
            $newClass->name = $clase['value'];
            $newClass->muzid = $clase['id'];
            $newClass->corpusesid = $clase['baseId'];
            $newClass->orders = $clase['order'];
            $newClass->save();
            //pa($newClass->product); exit;
///* / ВЫБИРАЕМ ТОВАР ИЗ БИТРИКСА ЕСЛИ ЕСТЬ ///* /         
            try{$bxProductId = $bx->bx24->getProduct($newClass->product)['ID'];}catch(\Exception $e){
                $bxProductId = (str_replace(['"', '}','{', '.', ','], '', explode(':', $e->getMessage())[6]));
            }
///* / ВЫБИРАЕМ МАССИВ ЦЕННИКОВ ИЗ РЕСУРСОВ ///* /
            $curPrice = $prices[(0 < $key = array_search($newClass->muzid, array_column($prices, 'muzid'))) ? $key : 0]['price'];            
///* / ЕСЛИ НЕТ ТОВАРА В БИТРИКСЕ, СОЗДАЁМ ТОВАР С ЦЕНОЙ КЛАССА ///* / 
            if('Product is not found' == $bxProductId || 'ID is not defined or invalid' == $bxProductId){
                $bxProductId = $newClass->product = $bx->bx24->addProduct([
                    'NAME' => $newClass->name, 
                    'CURRENCY_ID' => 'RUB', 
                    'PRICE' => $curPrice,
                ]);
            }
            $newClass->save();           
        } $rooms = array_fill_keys(array_keys($orders), '');
 /**/
        //pa($orders['a9b09c01-0057-41dd-a66b-a8c4ec5e4097']); exit; 
        //pa($orders); exit;
        $curOrders = array_fill_keys($curTimes, $rooms);
        $classes = Classes::all();
        //pa($classes->toArray());exit;
/**/        
        foreach($orders as $id => $ordersList){
///* / ВЫБИРАЕМ ID КЛАССА ИЗ НАШЕЙ БД ///* /
            $classMuzid = (0 <= $key = array_search($id, array_column($clases, 'id'))) ? $clases[$key]['id'] : null;
            //$classId = ($classMuzid) ? Classes::where('muzid', '=', $classMuzid)->get('id')->toArray()[0]['id'] : null;            
            $classId = array_search($classMuzid, array_column($classes->toArray(), 'muzid'));
            
            if(!empty($ordersList)){ //$cl = Classes::where('muzid', '=', $id); pa($cl, 5); exit;//pa($classId); exit; //pa($ordersList[$i]); exit;
 ///* / ПЕРЕБИРАЕМ ЗАКАЗЫ ИЗ МУЗБУКИНГА ///* /
                for($i=0,$c=count($ordersList); $i<$c; $i++){
///* / ЕСЛИ В ЗАКАЗЕ ЕСТЬ ИМЯ И ТЕЛЕФОН ///* /
                    if(!empty($phone = $ordersList[$i]['phone']) && !empty($fio = $ordersList[$i]['fio'])){
///* / ПРОВЕРЯЕМ ЕСТЬ ЛИ ПОЛЬЗОВАТЕЛЬ В БИТРИКС ПО ТЕЛЕФОНУ, ЕСЛИ НЕТ ///* /
                        if(empty($bxContactIdCheck = $bx->bx24->getContactsByPhone($phone))){
                            list($lastname, $firstname) = explode(' ', trim($fio));
///* / СОЗДАЁМ ПОЛЬЗОВАТЕЛЯ В БИТРИКС ///* /                            
                            $bxContactId = $bx->bx24->addContact([
                                'NAME'      => $firstname,
                                'LAST_NAME' => $lastname,
                                'PHONE'     => array((object)['VALUE' => $phone, 'VALUE_TYPE' => 'WORK']),
                                'EMAIL'     => array((object)['VALUE' => $email]),
                                //'COMPANY_ID'  => 332,
                                //'SECOND_NAME' => 'Васильевич',
                            ]);
///* / ЕСЛИ ПОЛЬЗОВАТЕЛЬ БИТРИКС СУЩЕСТВУЕТ БЕРЁМ ЕГО ID ///* /                            
                        }else{$bxContactId = $bxContactIdCheck[0]['ID'];}
                    }else{
///* / ЕСЛИ В ЗАКАЗЕ НЕТ ИМЯ И ТЕЛЕФОН ///* /
                        $importContact = $bx->bx24->getContact(7);
                        $phone = (empty($importContact['PHONE'][0]['VALUE'])) ? null : $importContact['PHONE'][0]['VALUE'];
                        $email = (empty($importContact['EMAIL'][0]['VALUE'])) ? null : $importContact['EMAIL'][0]['VALUE'];
                        $fio = $importContact['NAME'];
                        $bxContactId = $importContact['ID'];
                    }
                    $email = (empty($ordersList[$i]['email'])) ? '' : $ordersList[$i]['email'];
 ///* / ВЫБИРАЕМ ИЛИ СОЗДАЁМ ЕСЛИ НЕТ ПОЛЬЗОВАТЕЛЯ С ТАКИМ ТЕЛЕФОНО И ИМЕНЕМ В НАШЕЙ БАЗЕ ///* /
                    $newUser = Users::firstOrCreate(['phone' => $phone, 'fio' => $fio]);
                    $newUser->phone = $phone;
                    $newUser->fio = $fio;
                    $newUser->email = $email;
                    $newUser->bitrixid = $bxContactId;
                    $newUser->save();
                    
                    $userId = $newUser->id;
                    
                    $newOrder = Orders::firstOrCreate(['muzid' => $base['id']]);
                    
                    //pa($ordersList[$i]);
                    //if(10 == $i) exit;
                    
                    $newOrder->muzid = $ordersList[$i]['id']; 
                    $newOrder->classesid = $classId;
                    $newOrder->usersid = $userId;	
                    //$newOrder->deal = '';
                    $newOrder->datefrom = $ordersList[$i]['dateFrom'];
                    $newOrder->dateto = $ordersList[$i]['dateTo'];
                    //$newOrder->amountpeople = '';
                    $newOrder->comment = $ordersList[$i]['comment'];                    
                    $newOrder->save();
                    
///* / ВЫБИРАЕМ СДЕЛКУ ИЗ БИТРИКСА ЕСЛИ ЕСТЬ ///* /
                    try{$dealId = $bx->bx24->getDeal($newOrder->deal, [\App\Bitrix24\Bitrix24API::$WITH_PRODUCTS, \App\Bitrix24\Bitrix24API::$WITH_CONTACTS]);}catch(\Exception $e){
                        $dealId = str_replace(['"', '}','{', '.', ','], '', (explode('.', explode(':', $e->getMessage())[11])[0]));
                    }
///* / ЕСЛИ НЕТ СДЕЛКИ В БИТРИКСЕ, СОЗДАЁМ СДЕЛКУ ///* /
                    //$bxProductId = Classes::where();
                    if('ID is not defined or invalid' == $dealId){
                        $dealId = $bx->bx24->addDeal([
                            'TITLE' => $newOrder->muzid, 
                            'CONTACT_ID' => $bxContactId, 
                            'PRODUCTS' => array((object)["PRODUCT_ID" => $classes[$classId]['product']]),
                        ]);
                    }

                    //pa($classes[$classId]['product']);
                    //pa($dealId); exit;

                $periodsOrder = new \DatePeriod(new \DateTime(date('H:i', strtotime($ordersList[$i]['dateFrom']))), new \DateInterval('PT1H'), new \DateTime(date('H:i', strtotime($ordersList[$i]['dateTo'])))); 
                foreach($periodsOrder as $periodOrder){
                    //$curOrders[$periodOrder->format('H:00')][$id] = $ordersList[$i];
                    //unset(); 
                    $curOrders[$periodOrder->format('H:00')][$id] = $ordersList[$i]; //array_merge($rooms, [$id => $ordersList[$i]]);
                }
    }}}
    //pa($curOrders);
    //$curOrders = (empty($curOrders)) ? array_fill_keys($curTimes, $rooms) : array_merge(array_fill_keys($curTimes, $rooms),$curOrders);
    //pa($curOrders); exit;
    //
    /*/
    $deal = (!empty($deal_id) && $deal = CRest::call('crm.deal.get', ['ID' => $deal_id])) ? 
        (isset($deal['result']) ? $deal['result'] : ['ID' => $deal['error_description']]) : $deal;
    $deal_into_id = (empty($deal['UF_CRM_1683462809'])) ? null : $deal['UF_CRM_1683462809'];
///*/
 
///*/ Вывод ///*/
    $data = get_defined_vars(); unset($data['request'], $data['mb'], $data['bx']); $data = array_keys($data);
return view('front.home', compact($data));}else{
pa('Dont` connection internet.');}}             

}