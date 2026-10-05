// @ts-nocheck Material Tailwind 2.1.2 prop types conflict with this project's React 18 types.
"use client";

import React from "react";
import { Typography } from "@material-tailwind/react";
import {
  DocumentTextIcon,
  PlayCircleIcon,
  PencilSquareIcon,
  PhoneArrowDownLeftIcon,
} from "@heroicons/react/24/solid";

import StatsCard from "@/components/stats-card";


const STATS = [
  {
    icon: DocumentTextIcon,
    count: "10,200+",
    title: "Việc làm đang tuyển",
  },
  {
    icon: PlayCircleIcon,
    count: "50+",
    title: "Doanh nghiệp đối tác",
  },
  {
    icon: PencilSquareIcon,
    count: "10+",
    title: "Hồ sơ ứng viên",
  },
  {
    icon: PhoneArrowDownLeftIcon,
    count: "24/7",
    title: "Kết nối mỗi ngày",
  },
];

export function OutImpressiveStats() {
  return (
    <section className="px-8 pt-60">
      <div className="container mx-auto text-center lg:text-left">
        <div className="grid place-items-center text-center">
          <Typography variant="h2" color="blue-gray" className="mb-2 text-4xl">
            Cơ hội nghề nghiệp đang chờ bạn
          </Typography>
          <Typography
            variant="lead"
            className="mx-auto mb-24 w-full !text-gray-500 lg:w-5/12"
          >
            VIEJOB kết nối ứng viên với nhà tuyển dụng và những cơ hội phù hợp trên khắp Việt Nam.
          </Typography>
        </div>
        <div className="grid gap-y-16 gap-x-10 md:grid-cols-2 lg:grid-cols-4">
          {STATS.map((props, key) => (
            <StatsCard key={key} {...props} />
          ))}
        </div>
      </div>
    </section>
  );
}
export default OutImpressiveStats;
