<?php

declare(strict_types=1);

namespace Tests\app\Http\Response\Customer;

use OpenApi\Attributes as OA;

#[OA\Schema]
class EventResponse
{
    #[OA\Property]
    public bool $result;

    #[OA\Property(description: '이벤트 상세')]
    public Event $event;
}

#[OA\Schema]
class Event
{
    #[OA\Property(description: '이벤트 번호')]
    public int $no;
    #[OA\Property(description: '배너이미지')]
    public string $ds_thumb;
    #[OA\Property(description: '팝업이미지')]
    public string $ds_popup_thumb;
    #[OA\Property(description: '이벤트 상세페이지')]
    public string $ds_detail_url;
    #[OA\Property(description: '이벤트 제목')]
    public string $ds_title;
    #[OA\Property(description: '이벤트 내용')]
    public string $ds_content;
    #[OA\Property(description: '등록일시')]
    public string $dt_reg;
}
