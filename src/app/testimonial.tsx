// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import Image from "next/image";
import { Typography, Card, CardBody, Avatar } from "@material-tailwind/react";


function TESTIMONIAL() {
  return (
    <section className="px-8 py-40">
      <div className="container mx-auto">
        <Card color="transparent" shadow={false} className="">
          <CardBody className="col-span-full gap-10 place-items-center overflow-visible grid grid-cols-1 lg:grid-cols-4">
            <div className="w-full xl:w-[600px] flex items-center overflow-hidden rounded-xl justify-center col-span-2 h-full">
              <Image
                width={768}
                height={768}
                src="/image/blogs/blog6.svg"
                alt="testimonial image"
                className="w-full h-full scale-110 object-cover"
              />
            </div>
            <div className="col-span-2 w-full">
              <Typography variant="h6" color="blue" className="mb-4">
                CÂU CHUYỆN THÀNH CÔNG
              </Typography>
              <Typography
                variant="h3"
                color="blue-gray"
                className="mb-4 font-bold"
              >
                Tìm đúng cơ hội, phát triển đúng hướng
              </Typography>
              <Typography className="mb-1 w-full font-normal !text-gray-500">
                Hồ sơ hoàn chỉnh giúp bạn nổi bật hơn và nhận được những gợi ý việc làm phù hợp với kinh nghiệm, kỹ năng của mình.
              </Typography>
              <div className="grid mb-4">
                <div className="flex items-center gap-2">
                  <span className="h-1 w-1 bg-gray-500 rounded-full" />
                  <Typography className="w-full font-normal !text-gray-500">
                    Khám phá công việc từ doanh nghiệp uy tín
                  </Typography>
                </div>
                <div className="flex items-center gap-2">
                  <span className="h-1 w-1 bg-gray-500 rounded-full" />
                  <Typography className="w-full font-normal !text-gray-500">
                    Theo dõi hồ sơ ứng tuyển thuận tiện
                  </Typography>
                </div>
              </div>
              <div className="flex items-center mt-8 gap-4">
                <Avatar
                  variant="circular"
                  src="/image/avatar3.jpg"
                  alt="spotify"
                  size="md"
                />
                <div>
                  <Typography variant="h6" color="blue-gray" className="mb-0.5">
                    Nguyễn Minh Anh
                  </Typography>
                  <Typography
                    variant="small"
                    className="font-normal !text-gray-500"
                  >
                    Ứng viên Backend Developer
                  </Typography>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>
    </section>
  );
}

export default TESTIMONIAL;
