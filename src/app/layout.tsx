import "./globals.css";
import type { Metadata } from "next";
import { Inter } from "next/font/google";
import Navbar from "@/components/navbar";
import Footer from "@/components/footer";
import { MotionEffects } from "@/components/motion-effects";

const inter = Inter({ subsets: ["latin", "vietnamese"], display: "swap" });

export const metadata: Metadata = {
  title: "VIEJOB — Công việc phù hợp. Tương lai rộng mở.",
  description: "Khám phá việc làm, xây dựng hồ sơ và theo dõi cơ hội nghề nghiệp của bạn cùng VIEJOB.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="vi" className={inter.className}>
      <body>
        <a href="#main-content" className="skip-link">Đi đến nội dung chính</a>
        <MotionEffects />
        <Navbar />
        <main id="main-content">{children}</main>
        <Footer />
      </body>
    </html>
  );
}
