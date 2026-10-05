import "./globals.css";
import type { Metadata } from "next";
import { Roboto } from "next/font/google";
import { Layout, FixedPlugin, Navbar, Footer } from "@/components";

const roboto = Roboto({
  subsets: ["latin"],
  weight: ["300", "400", "500", "700", "900"],
  display: "swap",
});

export const metadata: Metadata = {
  title: "VIEJOB | Tìm việc làm phù hợp",
  description:
    "VIEJOB giúp bạn khám phá việc làm phù hợp, quản lý hồ sơ và theo dõi đơn ứng tuyển.",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="vi">
      <head>
        <script
          defer
          data-site="YOUR_DOMAIN_HERE"
          src="https://api.nepcha.com/js/nepcha-analytics.js"
        ></script>
        <link rel="shortcut icon" href="/favicon.png" type="image/png" />
      </head>
      <body className={roboto.className}>
        <Layout>
          <Navbar />
          {children}
          <FixedPlugin />
          <Footer />
        </Layout>
      </body>
    </html>
  );
}
