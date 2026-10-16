# ==============================================================================
# Isolated Android Build Worker for Appify (Flutter White-Label Engine)
# ==============================================================================
FROM ubuntu:22.04

LABEL maintainer="Appify Team <ops@appify.dev>"
LABEL description="Isolated Android/Flutter container runner for automated AAB compilation"

ENV DEBIAN_FRONTEND=noninteractive
ENV ANDROID_SDK_ROOT="/opt/android-sdk"
ENV ANDROID_HOME="/opt/android-sdk"
ENV FLUTTER_ROOT="/opt/flutter"
ENV PATH="${PATH}:${FLUTTER_ROOT}/bin:${ANDROID_SDK_ROOT}/cmdline-tools/latest/bin:${ANDROID_SDK_ROOT}/platform-tools"

# 1. Install system prerequisites & OpenJDK 17
RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    curl \
    git \
    unzip \
    xz-utils \
    zip \
    libglu1-mesa \
    openjdk-17-jdk \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# 2. Install Android Command-Line Tools & Accept Licenses
ARG ANDROID_CMD_TOOLS_VERSION="11076708"
RUN mkdir -p ${ANDROID_SDK_ROOT}/cmdline-tools \
    && curl -fsSL -o /tmp/cmdline-tools.zip "https://dl.google.com/android/repository/commandlinetools-linux-${ANDROID_CMD_TOOLS_VERSION}_latest.zip" \
    && unzip -q /tmp/cmdline-tools.zip -d ${ANDROID_SDK_ROOT}/cmdline-tools \
    && mv ${ANDROID_SDK_ROOT}/cmdline-tools/cmdline-tools ${ANDROID_SDK_ROOT}/cmdline-tools/latest \
    && rm /tmp/cmdline-tools.zip

# Accept licenses & install SDK packages
RUN yes | sdkmanager --licenses > /dev/null \
    && sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"

# 3. Install Flutter SDK (Pinned 3.22.x stable)
ARG FLUTTER_VERSION="3.22.2"
RUN curl -fsSL -o /tmp/flutter.tar.xz "https://storage.googleapis.com/flutter_infra_release/releases/stable/linux/flutter_linux_${FLUTTER_VERSION}-stable.tar.xz" \
    && tar -xf /tmp/flutter.tar.xz -C /opt \
    && rm /tmp/flutter.tar.xz \
    && git config --global --add safe.directory ${FLUTTER_ROOT}

# Pre-download Flutter artifacts
RUN flutter precache --android

# 4. Create isolated unprivileged builder user
RUN groupadd -g 1000 builder && useradd -u 1000 -g builder -m -s /bin/bash builder \
    && chown -R builder:builder ${FLUTTER_ROOT} ${ANDROID_SDK_ROOT}

USER builder
WORKDIR /workspace

# Verify installation health
RUN flutter doctor -v

CMD ["bash"]
