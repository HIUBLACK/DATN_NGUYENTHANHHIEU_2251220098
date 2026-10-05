// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import Image from "next/image";
import { Button, Typography, Card } from "@material-tailwind/react";

function Hero() {
  return (
    <div className="!flex h-[55vh] w-full items-center justify-between px-10">
      <Image
        width={1200}
        height={1200}
        src="/image/image8.svg"
        alt="bg-img"
        className="absolute inset-0 ml-auto w-[920px] h-[780px] rounded-bl-[100px] object-cover object-center"
      />
      <div className="container mx-auto mt-28">
        <div className="grid grid-cols-12 text-center lg:text-left">
          <Card className="col-span-full rounded-xl border border-white bg-white/90 py-10 p-8 shadow-lg shadow-black/10 backdrop-blur-sm backdrop-saturate-200 xl:col-span-7">
            <Typography
              variant="h1"
              color="blue-gray"
              className="lg:text-5xl !leading-snug text-3xl lg:max-w-3xl"
            >
              Tìm công việc phù hợp, phát triển sự nghiệp cùng VIEJOB
            </Typography>
            <Typography variant="lead" className="mb-10 mt-6 !text-gray-900">
              Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và mục tiêu của bạn. Tạo hồ sơ, quản lý CV và ứng tuyển dễ dàng trên một nền tảng.
            </Typography>
            <div className="mb-8 flex justify-center gap-4 lg:justify-start">
              <a href="#jobs"><Button color="blue">Khám phá việc làm</Button></a>
              <a href="#applications"><Button color="gray" variant="outlined">Đơn ứng tuyển</Button></a>
            </div>
            <div className="grid grid-cols-2 lg:grid-cols-4 items-center justify-between gap-4 lg:justify-start">
              <Image
                width={144}
                height={144}
                className="w-36 grayscale opacity-60"
                src="/logos/logo-pinterest.svg"
                alt="pinterest"
              />
              <Image
                width={144}
                height={144}
                className="w-36 grayscale opacity-60"
                src="/logos/logo-netflix.svg"
                alt="netflix"
              />
              <Image
                width={144}
                height={144}
                className="w-36 grayscale opacity-60"
                src="/logos/logo-coinbase.svg"
                alt="coinbase"
              />
              <Image
                width={144}
                height={144}
                className="w-36 grayscale opacity-60"
                src="/logos/logo-google.svg"
                alt="google"
              />
            </div>
          </Card>
        </div>
      </div>
    </div>
  );
}
export default Hero;
