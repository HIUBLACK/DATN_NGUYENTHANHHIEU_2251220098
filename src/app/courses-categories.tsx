// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import React from "react";
import {
  Button,
  Typography as MaterialTypography,
  Card,
  CardBody,
} from "@material-tailwind/react";
const Typography = MaterialTypography as React.ComponentType<any>;

import {
  GlobeEuropeAfricaIcon,
  MicrophoneIcon,
  PuzzlePieceIcon,
  HeartIcon,
} from "@heroicons/react/24/solid";

import CategoryCard from "@/components/category-card";


const CATEGORIES = [
  {
    img: "/image/blogs/blog-3.png",
    icon: HeartIcon,
    title: "Phát triển giao diện",
    desc: "1.240 việc làm",
  },
  {
    img: "/image/blogs/blog-12.jpeg",
    icon: PuzzlePieceIcon,
    title: "Phát triển Backend",
    desc: "860 việc làm",
  },
  {
    img: "/image/blogs/blog-10.jpeg",
    icon: GlobeEuropeAfricaIcon,
    title: "Phân tích dữ liệu",
    desc: "520 việc làm",
  },
  {
    img: "/image/blogs/blog-13.png",
    icon: MicrophoneIcon,
    title: "Thiết kế & Sản phẩm",
    desc: "430 việc làm",
  },
];

export function CoursesCategories() {
  return (
    <section className="container mx-auto px-8 py-36">
      <div className="mb-20 grid place-items-center text-center">
        <Typography variant="h2" color="blue-gray" className="my-3">
          Ngành nghề nổi bật
        </Typography>
        <Typography variant="lead" className="!text-gray-500 lg:w-6/12">
          Khám phá các lĩnh vực đang tuyển dụng sôi động và tìm cơ hội phù hợp với năng lực của bạn.
        </Typography>
      </div>
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <Card
          color="gray"
          className="relative grid h-full w-full place-items-center overflow-hidden text-center"
        >
          <div className="absolute inset-0 h-full w-full bg-gray-900/75" />
          <CardBody className="relative w-full">
            <Typography color="white" className="text-xs font-bold opacity-50">
              CƠ HỘI MỚI MỖI NGÀY
            </Typography>
            <Typography variant="h4" className="mt-9" color="white">
              Sự nghiệp tiếp theo của bạn bắt đầu tại đây
            </Typography>
            <Typography
              color="white"
              className="mt-4 mb-14 font-normal opacity-50"
            >
              Tìm kiếm hàng nghìn vị trí tuyển dụng từ những doanh nghiệp hàng đầu.
            </Typography>
            <Button size="sm" color="white">
              Tìm việc ngay
            </Button>
          </CardBody>
        </Card>
        <div className="col-span-1 flex flex-col gap-6">
          {CATEGORIES.slice(0, 2).map((props, key) => (
            <CategoryCard key={key} {...props} />
          ))}
        </div>
        <div className="col-span-1 flex flex-col gap-6">
          {CATEGORIES.slice(2, 4).map((props, key) => (
            <CategoryCard key={key} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}

export default CoursesCategories;
