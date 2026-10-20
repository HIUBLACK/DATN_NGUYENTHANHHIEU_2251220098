/** @type {import('next').NextConfig} */
const nextConfig = {
  // Allow production verification alongside a running development server.
  distDir: process.env.VIEJOB_DIST_DIR || ".next",
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: "**",
      },
    ],
  },
};

module.exports = nextConfig;
