<?php

declare(strict_types=1);

namespace Tests\app\Http\Response\Parking;

use OpenApi\Attributes as OA;

#[OA\Schema]
class ParkingSiteGets
{
    #[OA\Property(description: '성공 여부', example: true)]
    public bool $result;
    #[OA\Property(description: '상점 리스트', type: 'array', items: new OA\Items('#/components/schemas/ParkingSite'))]
    public ParkingSite $rows;
}

#[OA\Schema]
class ParkingSite
{
    #[OA\Property(description: '주차장 번호')]
    public int $no_site;
    #[OA\Property(description: '외부 주차장 고유번호')]
    public string $id_site;
    #[OA\Property(description: 'WEB: 웹할인권, AUTO: 자동결제')]
    public string $ds_type;
    #[OA\Property(description: '웹할인권 주차장 번호 (삭제 예정)', nullable: true)]
    public int $no_parking_site;
    #[OA\Property(description: '자동출차 주차장 번호 (삭제 예정)', nullable: true)]
    public string $id_auto_parking;
    #[OA\Property(description: '주차장명')]
    public string $nm_shop;
    #[OA\Property(description: '주차장 정보 태그 (,로 구분)', nullable: true)]
    public string $ds_option_tag;
    #[OA\Property(description: '시간당 요금(참고용 정보)', nullable: true)]
    public int $at_price;
    #[OA\Property(description: '주차장 상세 요금(참고용 정보)', nullable: true)]
    public string $ds_price_info;
    #[OA\Property(description: '주차장 시간 정보', nullable: true)]
    public string $ds_time_info;
    #[OA\Property(description: '주차장 전화번호', nullable: true)]
    public string $ds_tel;
    #[OA\Property(description: '주차장 안내정보', nullable: true)]
    public string $ds_info;
    #[OA\Property(description: '주차장 위도')]
    public float $at_lat;
    #[OA\Property(description: '주차장 경도')]
    public float $at_lng;
    #[OA\Property(description: '주차장 주소')]
    public string $ds_address;
    #[OA\Property(description: '운영 시간', nullable: true)]
    public string $ds_operation_time;
    #[OA\Property(description: '유의사항 (markdown)', nullable: true)]
    public string $ds_caution;
    #[OA\Property(description: '평일운영방법(1:24시간,2:시간제,3:휴무,4:정보없음)', nullable: true)]
    public string $auto_biz_type;
    #[OA\Property(description: '평일운영시간', nullable: true)]
    public string $auto_biz_time;
    #[OA\Property(description: '토요일운영방법(1:24시간,2:시간제,3:휴무,4:정보없음)', nullable: true)]
    public string $auto_sat_biz_type;
    #[OA\Property(description: '토요일운영시간', nullable: true)]
    public string $auto_sat_biz_time;
    #[OA\Property(description: '공휴일운영방법(1:24시간,2:시간제,3:휴무,4:정보없음)', nullable: true)]
    public string $auto_hol_biz_type;
    #[OA\Property(description: '공휴일운영시간', nullable: true)]
    public string $auto_hol_biz_time;
    #[OA\Property(description: 'PG사 수수료', nullable: true)]
    public float $at_pg_commission_rate;
    #[OA\Property(description: '수수료방식', nullable: true)]
    public float $cd_commission_type;
    #[OA\Property(description: '수수료 대상금액', nullable: true)]
    public float $at_commission_amount;
    #[OA\Property(description: '매장수수료율', nullable: true)]
    public float $at_commission_rate;
    #[OA\Property(description: '영업대행 수수료율', nullable: true)]
    public float $at_sales_commission_rate;
    #[OA\Property(description: '매장 사용여부')]
    public string $ds_status;
    #[OA\Property(description: '배치 업데이트 여부')]
    public string $use_yn;
    #[OA\Property(description: '등록일')]
    public string $dt_reg;
    #[OA\Property(description: '수정일')]
    public string $dt_upt;
    #[OA\Property(description: '주차장 이미지', type: 'array', items: new OA\Items('#/components/schemas/ParkingSiteImage'))]
    public ParkingSiteImage $parkingSiteImage;
    #[OA\Property(description: '주차장 상품', type: 'array', items: new OA\Items('#/components/schemas/ParkingSiteTicket'))]
    public ParkingSiteTicket $parkingSiteTicket;
}

#[OA\Schema]
class ParkingSiteImage
{
    #[OA\Property(description: '번호')]
    public int $no;
    #[OA\Property(description: '외부 주차장 고유번호')]
    public int $id_site;
    #[OA\Property(description: '웹할인권 주차장 번호 (삭제 예정)')]
    public int $no_parking_site;
    #[OA\Property(description: '이미지 번호')]
    public int $image_no;
    #[OA\Property(description: '이미지 url')]
    public string $ds_image_url;
}

#[OA\Schema]
class ParkingSiteTicket
{
    #[OA\Property(description: '상품 고유번호')]
    public int $no_product;
    #[OA\Property(description: '외부 주차장 고유번호')]
    public int $id_site;
    #[OA\Property(description: '웹할인권 주차장 번호 (삭제 예정)')]
    public int $no_parking_site;
    #[OA\Property(description: '할인권명')]
    public string $nm_product;
    #[OA\Property(description: '할인권 종류 코드')]
    public int $cd_ticket_type;
    #[OA\Property(description: '할인권 요일 코드')]
    public int $cd_ticket_day_type;
    #[OA\Property(description: '주차 가능 시간(시작)')]
    public string $ds_parking_start_time;
    #[OA\Property(description: '주차 종료 시간(종료)')]
    public string $ds_parking_end_time;
    #[OA\Property(description: '구매 가능 요일 (,로 구분)')]
    public string $ds_selling_days;
    #[OA\Property(description: '구매 가능 시간(시작)')]
    public string $ds_selling_start_time;
    #[OA\Property(description: '구매 가능 시간(종료)')]
    public string $ds_selling_end_time;
    #[OA\Property(description: '금액')]
    public int $at_price;
    #[OA\Property(description: "구입 상태('AVAILABLE','NOT_YET_TIME','SOLD_OUT')")]
    public string $cd_selling_status;
}



