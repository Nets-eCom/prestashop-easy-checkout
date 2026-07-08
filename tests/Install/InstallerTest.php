<?php

namespace Nexi\Checkout\Tests\Install;

use Nexi\Checkout\Install\Installer;
use Nexi\Checkout\Install\InstallStepInterface;
use PHPUnit\Framework\TestCase;

class InstallerTest extends TestCase
{
    public function testInstallSuccess(): void
    {
        $mockStep1 = $this->createMock(InstallStepInterface::class);
        $mockStep1->expects(self::once())
            ->method('install')
            ->willReturn(true);

        $mockStep2 = $this->createMock(InstallStepInterface::class);
        $mockStep2->expects(self::once())
            ->method('install')
            ->willReturn(true);

        $installer = new Installer($mockStep1, $mockStep2);

        $result = $installer->install();
        self::assertTrue($result);
    }

    public function testInstallFailureWhenOneStepReturnsFalse(): void
    {
        $mockStep1 = $this->createMock(InstallStepInterface::class);
        $mockStep1->expects(self::once())
            ->method('install')
            ->willReturn(true);

        $mockStep2 = $this->createMock(InstallStepInterface::class);
        $mockStep2->expects(self::once())
        ->method('install')
            ->willReturn(false);

        $mockStep3 = $this->createMock(InstallStepInterface::class);
        $mockStep3->expects(self::never())
            ->method('install');

        $installer = new Installer($mockStep1, $mockStep2, $mockStep3);

        $result = $installer->install();
        self::assertFalse($result);
    }
}
