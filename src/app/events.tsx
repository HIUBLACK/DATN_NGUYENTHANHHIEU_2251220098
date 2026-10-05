// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import React from "react";
import { Typography, Card, CardBody, CardHeader, Button } from "@material-tailwind/react";
import EventCard from "@/components/event-card";

const EVENTS = [
  {
    img: "/image/blogs/blog-1.svg",
    title: "Xu hướng tuyển dụng ngành công nghệ",
    desc: "Cập nhật những kỹ năng và vị trí được doanh nghiệp tìm kiếm nhiều nhất.",
    buttonLabel: "Xem cơ hội",
  },
  {
    img: "/image/blogs/blog2.svg",
    title: "Ngày hội việc làm công nghệ",
    desc: "Gặp gỡ nhà tuyển dụng và khám phá cơ hội nghề nghiệp mới.",
    buttonLabel: "Xem chi tiết",
  },
  {
    img: "/image/blogs/blog3.svg",
    title: "Bí quyết viết CV nổi bật",
    desc: "Tìm hiểu cách trình bày kinh nghiệm và kỹ năng để gây ấn tượng với nhà tuyển dụng.",
    buttonLabel: "Tìm hiểu thêm",
  },
  {
    img: "/image/blogs/blog4.svg",
    title: "Phỏng vấn hiệu quả cùng chuyên gia",
    desc: "Chuẩn bị tốt hơn cho buổi phỏng vấn và tự tin chinh phục công việc mơ ước.",
    buttonLabel: "Tìm hiểu thêm",
  },
];

export function Events() {
  return (
    <section className="py-20 px-8">
      <div className="container mx-auto mb-20 text-center">
        <Typography variant="h2" color="blue-gray" className="mb-4">
          Cẩm nang sự nghiệp
        </Typography>
        <Typography
          variant="lead"
          className="mx-auto w-full px-4 font-normal !text-gray-500 lg:w-6/12"
        >
          Kiến thức hữu ích giúp bạn hoàn thiện hồ sơ và sẵn sàng cho bước tiến tiếp theo.
        </Typography>
      </div>
      <div className="container mx-auto grid grid-cols-1 gap-x-10 gap-y-20 md:grid-cols-2 xl:grid-cols-4">
        {EVENTS.map((props, idx) => (
          <EventCard key={idx} {...props} />
        ))}
      </div>
    </section>
  );
}


export default Events;
