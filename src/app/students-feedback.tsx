// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";
import React from "react";
import FeedbackCard from "@/components/feedback-card";
import { Typography } from "@material-tailwind/react";


const FEEDBACKS = [
  {
    feedback:
      "Tôi tìm được công việc Backend phù hợp chỉ sau vài tuần. Các gợi ý giúp tôi tiết kiệm rất nhiều thời gian.",
    client: "Trần Hà My",
    title: "Lập trình viên Backend",
    img: "/image/avatar1.jpg",
  },
  {
    feedback:
      "Hồ sơ và CV được quản lý gọn gàng. Tôi dễ dàng theo dõi các đơn ứng tuyển của mình.",
    client: "Lê Hoàng Nam",
    title: "Kỹ sư phần mềm",
    img: "/image/avatar3.jpg",
  },
  {
    feedback:
      "Tôi thích cách hệ thống gợi ý việc làm dựa trên kỹ năng và kinh nghiệm của mình.",
    client: "Phạm Thu Trang",
    title: "Chuyên viên phân tích dữ liệu",
    img: "/image/avatar2.jpg",
  },
];

export function StudentsFeedback() {
  return (
    <section className="px-8 py-36">
      <div className="container mx-auto">
        <div className="mb-16 flex flex-col items-center w-full">
          <Typography variant="h2" color="blue-gray" className="mb-2">
            Ứng viên nói gì về VIEJOB
          </Typography>
          <Typography
            variant="lead"
            className="mb-10 max-w-3xl lg:text-center !text-gray-500"
          >
            Những trải nghiệm từ ứng viên đã tìm thấy cơ hội nghề nghiệp phù hợp.
          </Typography>
        </div>
        <div className="grid gap-x-8 gap-y-12 lg:px-32 grid-cols-1 md:grid-cols-3">
          {FEEDBACKS.map((props, key) => (
            <FeedbackCard key={key} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}


export default StudentsFeedback;
