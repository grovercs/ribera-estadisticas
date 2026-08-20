import type { NextConfig } from "next";
import path from "path";

const nextConfig: NextConfig = {
  allowedDevOrigins: ['192.168.200.176'],
  turbopack: {
    // Pin Turbopack workspace root to this Next.js app directory.
    root: path.resolve("."),
  },
};

export default nextConfig;
